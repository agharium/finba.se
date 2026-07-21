# Deployment

Production hosting for Finba.se on Google Cloud Run.

Monorepo path: **`apps/web`**. Run deploy/build commands from that directory (or use `./apps/web/scripts/...` from the repo root).

| Piece | Choice |
| --- | --- |
| Compute | Google Cloud Run (`southamerica-east1`) |
| App hostname | `https://app.finba.se` |
| Database | Supabase PostgreSQL |
| File storage | Supabase Storage via Laravel disk `finba` |
| Email | Resend |
| Auth | Email/password + Google OAuth |
| DNS | Cloudflare |
| Container | FrankenPHP (Caddy + PHP 8.4) on `$PORT` |

This guide documents how to deploy and operate the service. It does not provision GCP resources automatically.

Related docs: [architecture.md](architecture.md), [storage.md](storage.md), [pwa.md](pwa.md), [testing.md](testing.md).

## Architecture constraints

- Cloud Run containers are stateless and ephemeral.
- Do not store user uploads on the container filesystem. Use `FINBA_STORAGE_DISK=finba`.
- Sessions and cache use the `database` driver so state is shared across instances.
- Beta queues use `sync` (no dedicated worker service).
- No application scheduler is required yet.
- Migrations run through Cloud Run Job `finba-migrate`, never on every web boot.
- Logs go to stderr and into Cloud Logging.
- Health check: `GET /up`.

Production environment variables must be changed manually in Cloud Run / Secret Manager. Do not bake secrets into images or client-side assets.

## 1. GCP project bootstrap

```bash
gcloud auth login
gcloud config set project PROJECT_ID

gcloud services enable \
  run.googleapis.com \
  artifactregistry.googleapis.com \
  cloudbuild.googleapis.com \
  secretmanager.googleapis.com
```

Preferred region: `southamerica-east1`. Service name: `finba`.

```bash
gcloud artifacts repositories create finba \
  --repository-format=docker \
  --location=southamerica-east1 \
  --description="Finba.se application images"
```

## 2. Build the image

Run all commands from the Laravel app directory:

```bash
cd apps/web
```

```bash
GIT_SHA=$(git rev-parse --short HEAD)

gcloud builds submit \
  --tag southamerica-east1-docker.pkg.dev/PROJECT_ID/finba/app:$GIT_SHA
```

Or from anywhere in the repo:

```bash
PROJECT_ID=PROJECT_ID ./apps/web/scripts/deploy-cloud-run.sh
```

The script resolves its own `apps/web` directory and uses that Dockerfile / Cloud Build context.

The multi-stage `Dockerfile`:

1. Builds Vite assets.
2. Installs Composer production dependencies.
3. Runs FrankenPHP as non-root on `$PORT` (default `8080`).
4. Does not copy `.env`.
5. Does not bake config caches; the entrypoint runs `php artisan optimize` with runtime env/secrets.

### Local image validation

When Docker is available (from `apps/web`):

```bash
cd apps/web
docker build -t finba:local .
docker run --rm -p 8080:8080 \
  --env-file .env \
  -e PORT=8080 \
  -e APP_ENV=production \
  -e APP_DEBUG=false \
  -e LOG_CHANNEL=stderr \
  finba:local
```

Verify:

- `curl -i http://127.0.0.1:8080/up` returns 200
- Login page and `/build/...` assets load
- Logs appear on container stdout/stderr

## 3. Non-secret environment variables

Set on the Cloud Run service:

```env
APP_NAME=Finba.se
APP_ENV=production
APP_DEBUG=false
APP_URL=https://app.finba.se
APP_LOCALE=en
APP_FALLBACK_LOCALE=en
LOG_CHANNEL=stderr
LOG_LEVEL=info

# Leave ASSET_URL unset unless assets are served from another intentional origin.
# Relative panel logos (/images/logo/*) follow the browser host.

APP_VERSION=0.1.0-beta
APP_BUILD=
GIT_SHA=

DB_CONNECTION=pgsql
DB_SSLMODE=require
DB_PORT=5432

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
CACHE_STORE=database
QUEUE_CONNECTION=sync

FILESYSTEM_DISK=local
FINBA_STORAGE_DISK=finba

MAIL_MAILER=resend
MAIL_FROM_ADDRESS=noreply@your-verified-domain
MAIL_FROM_NAME=Finba.se

FINBA_FEEDBACK_RATE_LIMIT=8
FINBA_FEEDBACK_MAX_ATTACHMENT_KB=2048

# Geo API (apps/geo) — sole geographic source of truth
GEO_BASE_URL=https://geo.internal.example
GEO_CACHE=true
GEO_CACHE_COUNTRIES_TTL=86400
GEO_CACHE_COUNTRY_TTL=86400
GEO_CACHE_REGIONS_TTL=86400
GEO_CACHE_REGION_TTL=86400
GEO_CACHE_CITY_TTL=86400
```

Store `GEO_INTERNAL_API_KEY` in Secret Manager (server-side only). See [geo.md](geo.md).

`scripts/deploy-cloud-run.sh` updates `APP_BUILD`, `GIT_SHA`, and `APP_VERSION` on each deploy.

## 4. Secret Manager

Create secrets outside the repository:

```bash
printf '%s' 'VALUE' | gcloud secrets create SECRET_NAME --data-file=-
printf '%s' 'NEW_VALUE' | gcloud secrets versions add SECRET_NAME --data-file=-
```

| Secret name | Env var |
| --- | --- |
| `finba-app-key` | `APP_KEY` |
| `finba-db-host` | `DB_HOST` |
| `finba-db-database` | `DB_DATABASE` |
| `finba-db-username` | `DB_USERNAME` |
| `finba-db-password` | `DB_PASSWORD` |
| `finba-resend-api-key` | `RESEND_API_KEY` |
| `finba-google-client-id` | `GOOGLE_CLIENT_ID` |
| `finba-google-client-secret` | `GOOGLE_CLIENT_SECRET` |
| `finba-supabase-storage-access-key-id` | `SUPABASE_STORAGE_ACCESS_KEY_ID` |
| `finba-supabase-storage-secret-access-key` | `SUPABASE_STORAGE_SECRET_ACCESS_KEY` |
| `finba-supabase-storage-region` | `SUPABASE_STORAGE_REGION` |
| `finba-supabase-storage-bucket` | `SUPABASE_STORAGE_BUCKET` |
| `finba-supabase-storage-endpoint` | `SUPABASE_STORAGE_ENDPOINT` |
| `finba-feedback-email` | `FINBA_FEEDBACK_EMAIL` |

```bash
gcloud run services update finba \
  --region southamerica-east1 \
  --update-secrets=APP_KEY=finba-app-key:latest,DB_PASSWORD=finba-db-password:latest
```

Grant the runtime service account `roles/secretmanager.secretAccessor` on each secret. Never expose storage credentials, Resend keys, or `APP_KEY` through `VITE_*` variables.

## 5. Baseline Cloud Run deploy

```bash
gcloud run deploy finba \
  --image southamerica-east1-docker.pkg.dev/PROJECT_ID/finba/app:GIT_SHA \
  --region southamerica-east1 \
  --platform managed \
  --allow-unauthenticated \
  --port 8080 \
  --memory 1Gi \
  --cpu 1 \
  --min-instances 0 \
  --max-instances 3 \
  --concurrency 40 \
  --timeout 60
```

| Setting | Suggestion |
| --- | --- |
| CPU | 1 |
| Memory | 1 GiB |
| Min instances | 0 (raise to 1 if cold starts hurt OAuth) |
| Max instances | 2–5 |
| Concurrency | 20–40 |
| Timeout | 60s |

Public unauthenticated access is required for login, PWA assets, and OAuth callbacks.

## 6. Database

- Use `DB_SSLMODE=require`.
- Prefer the Supabase transaction pooler for Cloud Run.
- Keep max instances × concurrency conservative to avoid exhausting Postgres connections.
- Do not embed certificates or passwords in the image.

Validate storage separately with:

```bash
php artisan finba:storage-check
```

Do not run that command during image build.

## 7. Sessions, cache, queues

| Concern | Beta choice |
| --- | --- |
| Sessions | `database` |
| Cache | `database` |
| Queue | `sync` |
| Scheduler | not required |

```env
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
SESSION_DOMAIN=null
```

Laravel trusts proxies in `bootstrap/app.php` so HTTPS and `X-Forwarded-*` headers work behind Cloud Run, Cloudflare, and load balancers.

## 8. Migration job

```bash
PROJECT_ID=PROJECT_ID IMAGE=IMAGE_URL ./scripts/migrate-cloud-run.sh --create
PROJECT_ID=PROJECT_ID ./scripts/migrate-cloud-run.sh --yes
```

```bash
gcloud run jobs create finba-migrate \
  --image IMAGE \
  --region southamerica-east1 \
  --command php \
  --args artisan,migrate,--force

gcloud run jobs execute finba-migrate \
  --region southamerica-east1 \
  --wait
```

## 9. Custom domain

Host: `app.finba.se`.

Cloud Run native domain mapping is not available in every region. Do not assume a raw Cloudflare CNAME to `*.run.app` is always correct for Host/TLS.

Recommended path:

1. Google Cloud external Application Load Balancer
2. Serverless NEG → Cloud Run `finba`
3. Google-managed TLS certificate for `app.finba.se`
4. Cloudflare DNS to the load balancer
5. Confirm TLS mode compatibility with Cloudflare proxy settings

Domain checklist:

- [ ] Service healthy on `run.app`
- [ ] Certificate issued for `app.finba.se`
- [ ] `APP_URL=https://app.finba.se`
- [ ] `GOOGLE_REDIRECT_URL=https://app.finba.se/auth/google/callback`
- [ ] `ASSET_URL` unset unless a dedicated CDN is intentional
- [ ] Proxy forwards `X-Forwarded-Proto`, `X-Forwarded-Host`, `X-Forwarded-Port`
- [ ] Google OAuth origins/redirect updated
- [ ] Resend domain verified
- [ ] Secure cookies on HTTPS
- [ ] No mixed-content assets or `http://*.run.app` logo URLs
- [ ] PWA manifest/`start_url` resolve on the custom host

## 10. OAuth, mail, and storage URLs

Google Cloud Console:

- Authorized JavaScript origins: `https://app.finba.se`
- Redirect URI: `https://app.finba.se/auth/google/callback`

```env
APP_URL=https://app.finba.se
GOOGLE_REDIRECT_URL=https://app.finba.se/auth/google/callback
```

Filament SPA mode excludes `/auth/google/*` so Livewire does not fetch the Socialite redirect. The Google login control uses a full browser navigation.

Storage:

```env
FINBA_STORAGE_DISK=finba
```

Persist object paths in the database, never public URLs. Production forces HTTPS URL generation in `AppServiceProvider`.

## 11. PWA headers

`docker/Caddyfile` and the Laravel routes for `/service-worker.js`, `/manifest.webmanifest`, and `/offline.html` set `Cache-Control: no-cache` with the correct content types. See [pwa.md](pwa.md).

## 12. Backups

Supabase database:

- [ ] Confirm automatic backups / PITR on the current plan
- [ ] Create a manual export before inviting testers
- [ ] Document where the export is stored
- [ ] Practice restore verification at least once

Supabase Storage objects are not included in database backups. Decide retention expectations for `feedback/...` objects before claiming attachment disaster recovery.

## 13. CI/CD

The first beta may use Cloud Build plus `scripts/deploy-cloud-run.sh` manually.

Future improvement: GitHub Actions with Workload Identity Federation, without long-lived JSON service-account keys in GitHub.

## 14. Post-deploy verification

1. `GET /up`
2. Login page and static assets
3. Migrations via `finba-migrate`
4. Register / verify email / password login
5. Google OAuth
6. Feedback + private storage object + email
7. PWA install / offline page
8. Full checklist in [testing.md](testing.md)
