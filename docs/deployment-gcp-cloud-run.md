# Deploy Finba to Google Cloud Run

First beta production hosting for Finba.se.

| Piece | Choice |
| --- | --- |
| Compute | Google Cloud Run (`southamerica-east1`) |
| App hostname | `https://app.finba.se` |
| Database | Supabase PostgreSQL (external) |
| Object storage | Supabase Storage via Laravel disk `finba` |
| Email | Resend |
| Auth | email/password + Google OAuth |
| DNS | Cloudflare |
| Container | FrankenPHP (Caddy + PHP 8.4), listens on `$PORT` |

This guide prepares and documents deployment. It does **not** provision GCP resources for you.

---

## Architecture notes

- Cloud Run containers are **stateless** and **ephemeral**.
- Do not store user uploads on the container filesystem. Use `FINBA_STORAGE_DISK=finba`.
- Sessions and cache use the **database** driver (shared across instances).
- Queues for the first beta use **`sync`** (no worker service). Mail/feedback remain synchronous.
- No application scheduler is required yet (`routes/console.php` has no product schedules).
- Database migrations run via Cloud Run Job `finba-migrate`, never on every web instance boot.
- Logs go to **stderr** → Cloud Logging.
- Health check: `GET /up` (Laravel bootable check, no heavy DB probe).

Related docs:

- `docs/supabase-storage.md`
- `docs/pwa-hosting.md`
- `docs/beta-smoke-test.md`

---

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

Billing must be enabled on the project.

Preferred region: **`southamerica-east1`**.

Service name: **`finba`**.

Create Artifact Registry:

```bash
gcloud artifacts repositories create finba \
  --repository-format=docker \
  --location=southamerica-east1 \
  --description="Finba application images"
```

---

## 2. Build the image

```bash
GIT_SHA=$(git rev-parse --short HEAD)

gcloud builds submit \
  --tag southamerica-east1-docker.pkg.dev/PROJECT_ID/finba/app:$GIT_SHA
```

Or use:

```bash
PROJECT_ID=PROJECT_ID ./scripts/deploy-cloud-run.sh
```

The multi-stage `Dockerfile`:

1. builds Vite assets;
2. installs Composer production dependencies;
3. runs FrankenPHP as non-root on `$PORT` (default 8080);
4. does **not** copy `.env`;
5. does **not** bake config caches (entrypoint runs `php artisan optimize` with runtime env/secrets).

### Local image validation (when Docker is available)

```bash
docker build -t finba:local .
docker run --rm -p 8080:8080 \
  --env-file .env \
  -e PORT=8080 \
  -e APP_ENV=production \
  -e APP_DEBUG=false \
  -e LOG_CHANNEL=stderr \
  finba:local
```

Then verify:

- `curl -i http://127.0.0.1:8080/up` → 200
- login page loads
- static assets under `/build/...` load
- logs appear on the container stdout/stderr

---

## 3. Production non-secret environment variables

Set on the Cloud Run service (plain env vars):

```env
APP_NAME=Finba
APP_ENV=production
APP_DEBUG=false
APP_URL=https://app.finba.se
APP_LOCALE=pt_BR
APP_FALLBACK_LOCALE=en
LOG_CHANNEL=stderr
LOG_LEVEL=info

APP_VERSION=0.1.0-beta
APP_BUILD=            # set per deploy, UTC YYYYMMDDHHMMSS
GIT_SHA=              # short commit sha

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
```

`scripts/deploy-cloud-run.sh` updates build metadata (`APP_BUILD`, `GIT_SHA`, `APP_VERSION`) on each deploy.

---

## 4. Secret Manager

Create secrets (values never committed):

```bash
printf '%s' 'VALUE' | gcloud secrets create SECRET_NAME --data-file=-
# later
printf '%s' 'NEW_VALUE' | gcloud secrets versions add SECRET_NAME --data-file=-
```

Recommended secret names → Cloud Run env bindings:

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

Bind example:

```bash
gcloud run services update finba \
  --region southamerica-east1 \
  --update-secrets=APP_KEY=finba-app-key:latest,DB_PASSWORD=finba-db-password:latest
```

Grant the Cloud Run runtime service account `roles/secretmanager.secretAccessor` on each secret.

Never put Supabase S3 credentials, Resend keys, or `APP_KEY` into Vite/`VITE_*` variables.

---

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

Initial resource guidance (conservative for Supabase connection pressure):

| Setting | Suggestion |
| --- | --- |
| CPU | 1 |
| Memory | 1 GiB |
| Min instances | 0 (raise to 1 if cold starts hurt OAuth) |
| Max instances | 2–5 |
| Concurrency | 20–40 |
| Timeout | 60s for normal web |

Public unauthenticated access is required for the web app (login, PWA, OAuth callback).

---

## 6. Database (Supabase)

- Use SSL: `DB_SSLMODE=require`.
- Prefer Supabase **pooler** connection for Cloud Run.
- Keep max instances × concurrency conservative to avoid exhausting Postgres connections.
- Do not migrate to Cloud SQL for this beta.
- Do not embed certificates/passwords in the image.

Validate app connectivity after deploy (login flow). Deeper storage validation:

```bash
# One-off Cloud Run Job or local with production secrets carefully loaded
php artisan finba:storage-check
```

Do **not** run `finba:storage-check` during image build.

---

## 7. Sessions, cache, queues, scheduler

| Concern | Beta choice |
| --- | --- |
| Sessions | `database` (shared) |
| Cache | `database` (shared) |
| Queue | `sync` (no worker) |
| Scheduler | not required |

Redis/Memorystore is a future optimization.

Secure cookies for `app.finba.se`:

```env
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
SESSION_DOMAIN=null
```

Laravel trusts proxies (`bootstrap/app.php`) so HTTPS/`X-Forwarded-*` work behind Cloud Run / Cloudflare / load balancers.

---

## 8. Migration Cloud Run Job

Never migrate on web startup.

```bash
PROJECT_ID=PROJECT_ID IMAGE=IMAGE_URL ./scripts/migrate-cloud-run.sh --create
# attach the same DB secrets/env as the web service, then:
PROJECT_ID=PROJECT_ID ./scripts/migrate-cloud-run.sh --yes
```

Equivalent manual commands:

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

---

## 9. Custom domain + Cloudflare

Host: **`app.finba.se`**.

Cloud Run native domain mapping is not available in every region (including potentially `southamerica-east1`). Do not assume a raw Cloudflare CNAME to `*.run.app` is always correct for Host/TLS.

### Beta / simple path

1. Deploy and test on the Cloud Run HTTPS URL (`*.run.app`).
2. Confirm `/up`, login, OAuth (with temporary authorized origins), mail, storage.
3. Only then attach custom domain routing.

### Recommended robust path

1. Google Cloud external Application Load Balancer
2. Serverless NEG → Cloud Run `finba`
3. Google-managed TLS certificate for `app.finba.se`
4. Cloudflare DNS A/AAAA (or CNAME when appropriate) to the load balancer IP
5. Decide Cloudflare proxy mode (orange/grey cloud) with TLS compatibility in mind

### Domain launch checklist

- [ ] Service healthy on `run.app`
- [ ] Certificate issued for `app.finba.se`
- [ ] `APP_URL=https://app.finba.se`
- [ ] Google OAuth origins/redirect updated
- [ ] Resend domain / from-address verified
- [ ] Cookies secure on HTTPS
- [ ] No mixed-content assets
- [ ] PWA manifest/`start_url` resolves on the custom host
- [ ] Cloudflare DNS only changed after LB/target works

---

## 10. OAuth, mail, storage URLs

Google Cloud Console:

- Authorized JavaScript origins: `https://app.finba.se`
- Redirect URI: `https://app.finba.se/auth/google/callback`

Also set:

```env
GOOGLE_REDIRECT_URL=https://app.finba.se/auth/google/callback
```

Resend:

- production API key in Secret Manager
- verified sending domain
- `FINBA_FEEDBACK_EMAIL` destination

Storage:

```env
FINBA_STORAGE_DISK=finba
```

Private bucket only. Paths in DB, never public URLs.

Production forces HTTPS URLs in `AppServiceProvider`.

---

## 11. PWA headers

Production Caddy config (`docker/Caddyfile`) sets:

- `/service-worker.js` → `Cache-Control: no-cache`
- `/manifest.webmanifest` → correct manifest MIME + `no-cache`
- `/offline.html` → `no-cache`

Laravel routes in `routes/web.php` apply the same headers when those assets are served through PHP.

---

## 12. Backup checklist (verify in provider UI)

Supabase database:

- [ ] Confirm automatic backups / PITR available on the current plan
- [ ] Create a manual export/dump before inviting testers
- [ ] Document where the export is stored
- [ ] Practice a restore (or restore verification steps) at least once

Supabase Storage:

- [ ] Understand that bucket objects are **not** included in DB backups
- [ ] Decide retention expectations for `feedback/...` objects
- [ ] Do not claim attachment disaster recovery until verified

Do not claim “backups exist” until checked in the Supabase dashboard.

---

## 13. CI/CD future work

First beta may use Cloud Build + `scripts/deploy-cloud-run.sh` manually.

Future improvement (not blocking):

- GitHub Actions + **Workload Identity Federation**
- no long-lived JSON service-account keys in GitHub

---

## 14. Post-deploy verification order

1. `GET /up`
2. Login page + CSS/JS assets
3. `php artisan migrate` via `finba-migrate`
4. Register / verify email / password login
5. Google OAuth
6. Feedback + private storage object + email
7. PWA install / offline page
8. Follow `docs/beta-smoke-test.md`

Sentry remains deferred.
