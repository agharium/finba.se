# Cloud Run production infrastructure — Finba Geo (`apps/geo`)

Public hostname: **geo.finba.se** (Cloudflare → Cloud Run)

Contract: do not change `/v1/*` response shapes. This document is infrastructure only.

Monorepo path: all Geo commands and the production Dockerfile live under `apps/geo/`.

After the one-time GCP / Cloudflare / GitHub bootstrap below, production deploys are:

```text
git push origin main   # changes under apps/geo/** (or workflow_dispatch)
```

Workflow: [`.github/workflows/geo-deploy.yml`](../../.github/workflows/geo-deploy.yml)

---

## 1. Prerequisites

| Item | Notes |
|------|--------|
| GCP project | Billing enabled; you choose the ID (do not hardcode in app code) |
| GitHub repo | Actions enabled; environment `production` |
| Cloudflare | Zone for `finba.se` |
| Local tools (bootstrap only) | `gcloud`, `gh` optional |

Enable APIs once:

```bash
PROJECT_ID="YOUR_GCP_PROJECT"
gcloud config set project "$PROJECT_ID"

gcloud services enable \
  run.googleapis.com \
  artifactregistry.googleapis.com \
  secretmanager.googleapis.com \
  iam.googleapis.com \
  iamcredentials.googleapis.com \
  cloudresourcemanager.googleapis.com \
  logging.googleapis.com \
  monitoring.googleapis.com \
  errorreporting.googleapis.com
```

Recommended region example (choose yours): `southamerica-east1`.

---

## 2. Artifact Registry

Create a Docker repository (name example: `finba-geo`):

```bash
REGION="YOUR_REGION"
REPO="finba-geo"

gcloud artifacts repositories create "$REPO" \
  --repository-format=docker \
  --location="$REGION" \
  --description="Finba Geo API container images" \
  --project="$PROJECT_ID"
```

Image URI pattern (no hardcoded project in source):

```text
REGION-docker.pkg.dev/PROJECT_ID/finba-geo/geo-api:TAG
```

Tags pushed by CI:

- `sha-<12-char-commit>`
- full `GITHUB_SHA`

### IAM for the deploy service account

Grant on the Artifact Registry repository (or project):

| Role | Why |
|------|-----|
| `roles/artifactregistry.writer` | Push images from GitHub Actions |
| `roles/artifactregistry.reader` | Cloud Run pull (often via Cloud Run agent; grant reader to the runtime SA if pulls fail) |

---

## 3. Workload Identity Federation (OIDC)

**Do not use service-account JSON keys.**

GitHub Actions authenticates with Google via OIDC short-lived tokens.

### 3.1 Create the deploy service account

```bash
SA_NAME="geo-deploy"
SA_EMAIL="${SA_NAME}@${PROJECT_ID}.iam.gserviceaccount.com"

gcloud iam service-accounts create "$SA_NAME" \
  --display-name="Finba Geo GitHub deploy" \
  --project="$PROJECT_ID"
```

### 3.2 Project roles for deploy

| Role | Why |
|------|-----|
| `roles/run.admin` | Deploy / update Cloud Run services and revisions |
| `roles/iam.serviceAccountUser` | Act as the Cloud Run runtime service account |
| `roles/artifactregistry.writer` | Push images |
| `roles/secretmanager.secretAccessor` | Mount secrets on Cloud Run + smoke-test key read |
| `roles/logging.viewer` | Optional: diagnose failed deploys from CI |

```bash
for ROLE in \
  roles/run.admin \
  roles/iam.serviceAccountUser \
  roles/artifactregistry.writer \
  roles/secretmanager.secretAccessor
do
  gcloud projects add-iam-policy-binding "$PROJECT_ID" \
    --member="serviceAccount:${SA_EMAIL}" \
    --role="$ROLE"
done
```

Also grant the **Cloud Run runtime** service account (default compute SA or a dedicated runtime SA) Secret Manager accessor on the API key secrets if you use a custom runtime identity.

### 3.3 Create the Workload Identity Pool and provider

```bash
POOL_ID="github-actions"
PROVIDER_ID="github"
GITHUB_ORG="YOUR_GITHUB_ORG_OR_USER"
GITHUB_REPO="YOUR_REPO_NAME"   # e.g. finba.se

gcloud iam workload-identity-pools create "$POOL_ID" \
  --location="global" \
  --display-name="GitHub Actions" \
  --project="$PROJECT_ID"

gcloud iam workload-identity-pools providers create-oidc "$PROVIDER_ID" \
  --location="global" \
  --workload-identity-pool="$POOL_ID" \
  --display-name="GitHub" \
  --issuer-uri="https://token.actions.githubusercontent.com" \
  --attribute-mapping="google.subject=assertion.sub,attribute.actor=assertion.actor,attribute.repository=assertion.repository,attribute.ref=assertion.ref" \
  --attribute-condition="assertion.repository=='${GITHUB_ORG}/${GITHUB_REPO}'" \
  --project="$PROJECT_ID"
```

### 3.4 Allow GitHub to impersonate the SA

```bash
PROJECT_NUMBER="$(gcloud projects describe "$PROJECT_ID" --format='value(projectNumber)')"
POOL_RESOURCE="projects/${PROJECT_NUMBER}/locations/global/workloadIdentityPools/${POOL_ID}"

gcloud iam service-accounts add-iam-policy-binding "$SA_EMAIL" \
  --project="$PROJECT_ID" \
  --role="roles/iam.workloadIdentityUser" \
  --member="principalSet://iam.googleapis.com/${POOL_RESOURCE}/attribute.repository/${GITHUB_ORG}/${GITHUB_REPO}"
```

Provider resource name (store in GitHub Secrets as `GCP_WORKLOAD_IDENTITY_PROVIDER`):

```bash
gcloud iam workload-identity-pools providers describe "$PROVIDER_ID" \
  --location="global" \
  --workload-identity-pool="$POOL_ID" \
  --project="$PROJECT_ID" \
  --format='value(name)'
```

### 3.5 GitHub Secrets (bootstrap only)

Create a GitHub **Environment** named `production` and add:

| Secret | Example / source |
|--------|------------------|
| `GCP_PROJECT_ID` | Your project id |
| `GCP_REGION` | e.g. `southamerica-east1` |
| `ARTIFACT_REGISTRY_REPO` | `finba-geo` |
| `CLOUD_RUN_SERVICE` | `finba-geo` |
| `GCP_SERVICE_ACCOUNT` | `geo-deploy@PROJECT_ID.iam.gserviceaccount.com` |
| `GCP_WORKLOAD_IDENTITY_PROVIDER` | Full provider resource name from above |

Do **not** store `GEO_INTERNAL_API_KEY` or trusted keys in GitHub Secrets when Secret Manager is available.

Workflow permissions already include `id-token: write` for OIDC.

---

## 4. Secret Manager

Runtime secrets are mounted into Cloud Run as environment variables. Non-secret settings (`GEO_ENV`, `GEO_TRUST_PROXY_HEADERS`, …) stay as normal Cloud Run env vars.

| Secret Manager secret | Cloud Run env | Required? | Purpose |
|-----------------------|---------------|-----------|---------|
| `GEO_INTERNAL_API_KEY` | `GEO_INTERNAL_API_KEY` | **Yes for Finba** | Single key for Laravel → Geo **internal** rate-limit tier |
| `GEO_TRUSTED_API_KEYS` | `GEO_TRUSTED_API_KEYS` | Optional (empty OK) | Comma-separated **trusted** keys (partners / automation) |

There is no separate `PUBLIC_KEYS` / `GEO_INTERNAL_KEYS` app variable. Public access is anonymous (IP-limited). Use the names the binary already reads.

**Do not** create or mount a Cloud Run secret named `GITHUB_TOKEN`. That variable is only for local/CI catalog tooling (`cmd/release`, `cmd/download`, `cmd/update`). The deploy workflow’s `${{ secrets.GITHUB_TOKEN }}` is GitHub Actions’ built-in token for the job — it is **not** injected into the Geo Cloud Run service.

### Generate keys

Use cryptographically random values (≥ 32 bytes of entropy). Examples:

```bash
# Internal (one value)
INTERNAL_KEY="$(openssl rand -base64 32)"
echo "$INTERNAL_KEY"   # copy once into Secret Manager + Laravel GEO_INTERNAL_API_KEY

# Trusted (zero or more; join with commas, no spaces required)
TRUSTED_A="$(openssl rand -base64 32)"
TRUSTED_B="$(openssl rand -base64 32)"
TRUSTED_KEYS="${TRUSTED_A},${TRUSTED_B}"   # or leave empty: TRUSTED_KEYS=""

# From apps/geo:
# make gen-api-key
```

Format: opaque string (base64/hex/alphanumeric). No max length enforced by the API; avoid whitespace inside a key. Trusted list is comma-separated; empties and duplicates are ignored.

### Create secrets once

```bash
# Generate strong values offline; do not commit them.
printf '%s' "$INTERNAL_KEY" | gcloud secrets create GEO_INTERNAL_API_KEY \
  --data-file=- --project="$PROJECT_ID"

# Trusted keys: comma-separated, or empty placeholder so --update-secrets succeeds.
printf '%s' "$TRUSTED_KEYS" | gcloud secrets create GEO_TRUSTED_API_KEYS \
  --data-file=- --project="$PROJECT_ID"

# Later rotations:
printf '%s' "$INTERNAL_KEY" | gcloud secrets versions add GEO_INTERNAL_API_KEY \
  --data-file=- --project="$PROJECT_ID"
```

Grant accessor to the identity Cloud Run uses at runtime (often the default compute SA):

```bash
RUNTIME_SA="$(gcloud iam service-accounts list --project="$PROJECT_ID" \
  --filter='displayName:Default compute service account' \
  --format='value(email)' | head -n1)"

for SECRET in GEO_INTERNAL_API_KEY GEO_TRUSTED_API_KEYS; do
  gcloud secrets add-iam-policy-binding "$SECRET" \
    --project="$PROJECT_ID" \
    --member="serviceAccount:${RUNTIME_SA}" \
    --role="roles/secretmanager.secretAccessor"
  gcloud secrets add-iam-policy-binding "$SECRET" \
    --project="$PROJECT_ID" \
    --member="serviceAccount:${SA_EMAIL}" \
    --role="roles/secretmanager.secretAccessor"
done
```

After rotating a secret version, redeploy (or update the service) so new revisions pick up `:latest`.

---

## 5. Cloud Run configuration

| Setting | Production value | Reason |
|---------|------------------|--------|
| CPU | **1** | Read-only SQLite + JSON; short CPU bursts |
| Memory | **512Mi** | Catalog ~16 MiB on disk; headroom for Go, SQLite, limiter map |
| Concurrency | **80** | Read-only DB with `MaxOpenConns(1)`; moderate parallelism per instance |
| Min instances | **1** | Avoid cold starts for Finba backend latency |
| Max instances | **5** | Bound cost and aggregate per-instance rate-limit capacity |
| Timeout | **30s** | Handlers are fast; margin under Cloudflare |
| Port | **8080** | Matches `PORT` / Dockerfile |
| Ingress | **all** | Public via Cloudflare DNS to Cloud Run URL / mapped domain |
| Auth | **Allow unauthenticated** | App-layer API keys + rate limits; IAM would block Finba without identity tokens |
| Execution env | **gen2** | Current Cloud Run default generation |
| CPU boost | **on** | Faster startup when scaling from zero (safety if min is later lowered) |

Non-secret environment variables set by deploy:

```env
GEO_ENV=production
GEO_DATABASE_PATH=/app/data/geo.db
LOG_LEVEL=info
PORT=8080
# GEO_TRUST_PROXY_HEADERS=true   # only after trusted proxy path is locked down (see below)
```

### `GEO_TRUST_PROXY_HEADERS` (deployment opt-in)

The binary and image default to **not** trusting proxy headers (`false` / unset). The deploy workflow does **not** set this variable.

Do **not** bake `GEO_TRUST_PROXY_HEADERS=true` into the Dockerfile. That would encode a network-topology assumption in every image tag and is unsafe while the service is reachable on its public `*.run.app` URL: clients can spoof `CF-Connecting-IP` / `X-Forwarded-For` because the app does not validate that the peer is Cloudflare.

This is a **deployment concern**, not an application bug. Forward-header trust is a deliberate switch for environments where the edge already sanitizes those headers.

Enable `true` on the Cloud Run service only when **all** of the following are true:

1. Every request reaches the application through a trusted reverse proxy (for example Cloudflare → Cloud Run).
2. Direct access to the `*.run.app` endpoint is prevented or is otherwise not a bypass of that proxy.
3. Proxy headers are considered trustworthy on that path.

Example (after the checklist is satisfied):

```bash
gcloud run services update "$CLOUD_RUN_SERVICE" \
  --project="$PROJECT_ID" \
  --region="$REGION" \
  --update-env-vars=GEO_TRUST_PROXY_HEADERS=true
```

Until then, leave it unset so public rate limiting uses `RemoteAddr`.

Optional HTTP timeouts (`HTTP_READ_TIMEOUT`, `HTTP_WRITE_TIMEOUT`, `HTTP_IDLE_TIMEOUT`) keep binary defaults unless you have a measured need to override.

### Revision labels

Each deploy sets labels (also visible on revisions via service update):

| Label | Example | Meaning |
|-------|---------|---------|
| `service` | `finba-geo` | Product identity |
| `environment` | `production` | Deploy target |
| `commit` | `a1b2c3d4e5f6` | Short git SHA |
| `version` | `sha-a1b2c3d4e5f6` | Image / build version tag |

### Rolling revisions

`gcloud run deploy` / `deploy-cloudrun` creates a **new revision** and shifts traffic only when the revision becomes ready. Previous revisions remain listed for rollback. The workflow waits for `Ready=True` and then runs smoke tests; failure fails the GitHub job (investigate / rollback manually if a bad revision already received traffic).

---

## 6. Domain: geo.finba.se (Cloudflare)

### Option A — Cloudflare CNAME to Cloud Run URL (simplest)

1. Deploy once; note `https://SERVICE-xxxxx-REGION.a.run.app`.
2. In Cloudflare DNS for `finba.se`:
   - Type: **CNAME**
   - Name: `geo`
   - Target: `SERVICE-xxxxx-REGION.a.run.app` (Cloudflare may require the proxied hostname without `https://`)
   - Proxy: **Proxied** (orange cloud)
3. SSL/TLS mode: **Full (strict)**.
4. Optional: WAF + rate limiting for anonymous `/v1/*`.
5. Propagation: usually minutes; up to 24h for resolvers.

Cloud Run must accept the `Host: geo.finba.se` header. Prefer **Option B** for first-party hostname verification.

### Option B — Cloud Run domain mapping + Cloudflare DNS

```bash
gcloud beta run domain-mappings create \
  --service="$CLOUD_RUN_SERVICE" \
  --domain="geo.finba.se" \
  --region="$REGION" \
  --project="$PROJECT_ID"
```

Follow the printed DNS records (often a CNAME or records under `ghs.googlehosted.com`). Create them in Cloudflare.

- Start with **DNS only** (grey cloud) until Google certificate provisioning succeeds, then switch to **Proxied** if you want Cloudflare WAF/CDN in front.
- Or keep Full Strict with Cloudflare origin certs once mapping is verified.

Expected:

- DNS propagation: minutes–hours
- Managed certificate: often 15–60 minutes after correct DNS

Do **not** put `GEO_INTERNAL_API_KEY` in browser JavaScript. Intended flow:

```text
Browser → Finba Laravel → Geo (internal key) → Cloud Run
```

---

## 7. Cache and scaling

| Concern | Behavior |
|---------|----------|
| SQLite catalog | Immutable file baked into the image; not a mutable cache |
| In-memory structures | Process-local (limiter buckets, etc.) |
| Request memoization | None required for this API |
| Rate limiter | **Per Cloud Run instance** |

Cloud Run instances are ephemeral. Losing in-memory limiter state on scale-down/restart is acceptable: limits re-arm; the service stays correct.

Multiple instances mean aggregate public capacity can exceed the configured per-instance RPM. That is acceptable for process protection and loop protection. For global abuse control, add **Cloudflare rate rules** (edge). A future **Redis**-backed limiter is optional and not implemented here.

---

## 8. Monitoring

Use Google Cloud only (no third-party APM in this setup):

| Product | Use |
|---------|-----|
| **Cloud Logging** | Structured JSON from the container (`slog`); filter by service name / revision |
| **Cloud Monitoring** | Request count, latency, instance count, CPU/memory |
| **Error Reporting** | Panic / non-zero exit and correlating error logs |
| **Cloud Run metrics** | `request_latencies`, `request_count`, `container/billable_instance_time` |

Suggested log-based metrics / charts:

- 5xx rate by revision
- p95 latency for `/v1/cities/search`
- Instance count vs concurrency

---

## 9. Alerting (document only — no Terraform)

Create alert policies in Monitoring (Console or gcloud) for:

| Alert | Signal idea |
|-------|-------------|
| Service unavailable | Uptime check on `https://geo.finba.se/health` failing |
| 5xx spike | Cloud Run request count with `response_code_class=5xx` above threshold |
| Container crash loop | Restart / failed instance count rising |
| Deploy / smoke failure | GitHub Actions workflow `geo-deploy` failure notification (repo settings or ChatOps) |
| Latency regression | p95 latency sustained above SLO |

Wire notifications to email / Pager / Chat as your ops channel prefers.

---

## 10. Smoke tests (CI)

After deploy, the workflow must pass or the job fails:

1. `GET /health` → 200, `"status":"ok"`
2. `GET /version` → 200 (build metadata)
3. `GET /v1/version` → 200 (dataset metadata)
4. `GET /v1/countries` with `X-API-Key` from Secret Manager `GEO_INTERNAL_API_KEY` → 200, non-empty JSON array

---

## 11. Rollback

List revisions:

```bash
gcloud run revisions list \
  --service="$CLOUD_RUN_SERVICE" \
  --region="$REGION" \
  --project="$PROJECT_ID"
```

Route 100% traffic to a previous revision:

```bash
gcloud run services update-traffic "$CLOUD_RUN_SERVICE" \
  --region="$REGION" \
  --project="$PROJECT_ID" \
  --to-revisions=REVISION_NAME=100
```

Or redeploy a known-good image digest/tag from Artifact Registry.

Keep previous images; do not delete tags immediately after deploy.

---

## 12. Catalog updates

The SQLite file is **immutable inside the image**. CI runs `go run ./cmd/update --force` (when `data/geo.db` is absent) and strict inspect before `docker build`. Refreshing supplier data in production means a new image + new revision (normal `git push` deploy or `workflow_dispatch`).

Do not write the catalog on Cloud Run’s ephemeral filesystem at runtime.

---

## 13. Local development (unchanged)

```bash
cd apps/geo
go run ./cmd/api
# or
make docker-build && make docker-run
```

Local Docker still requires `data/geo.db` (`make update` / `make import`). No Secret Manager required locally—use env vars.

---

## 14. End-to-end sequence (`git push` → production)

1. Push to `main` touching `apps/geo/**` (or run **geo-deploy** manually).
2. GitHub Actions `geo-deploy` starts (environment `production`).
3. `go test ./...`
4. Build or reuse `data/geo.db`; strict inspect.
5. OIDC → impersonate deploy SA (no JSON key).
6. `docker build` + push to Artifact Registry (`finba-geo/geo-api`).
7. Cloud Run deploy new revision (labels, secrets, env); traffic after ready.
8. Wait `Ready=True`.
9. Smoke: `/health`, `/version`, `/v1/version`, authenticated `/v1/countries`.
10. Job success → production serving the new revision.

---

## 15. Remaining manual steps (one-time)

Checklist:

- [ ] Enable GCP APIs
- [ ] Create Artifact Registry repo `finba-geo`
- [ ] Create deploy SA + WIF pool/provider + IAM bindings
- [ ] Create Secret Manager secrets + accessors
- [ ] Create GitHub environment `production` + bootstrap secrets
- [ ] First successful `workflow_dispatch` or push deploy
- [ ] Map `geo.finba.se` (Cloudflare + optional domain mapping)
- [ ] SSL Full Strict + optional WAF/rate rules
- [ ] Uptime check + alert policies
- [ ] Confirm Finba Laravel uses Secret Manager / env for internal key (server-side only)

---

## 16. Limitations

| Limitation | Impact |
|------------|--------|
| In-memory rate limits are per instance | Aggregate capacity grows with max instances |
| Catalog baked in image | Dataset change requires rebuild/redeploy |
| No Terraform in-repo | Bootstrap is documented shell, not IaC |
| OIDC attribute condition is repository-scoped | Tighten further to `ref:refs/heads/main` if desired |
| Smoke tests hit Cloud Run URL | Domain mapping issues are separate from deploy smoke |
| `-race` CI not required here | Deploy uses `go test ./...` without CGO race |
