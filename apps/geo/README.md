# Finba Geo

Read-only geographical catalog service for Finba. Imports the dr5hn cities CSV into a compact SQLite database and exposes countries, regions, and cities over a small REST API.

In the monorepo this package lives at **`apps/geo`**. See the [repository root README](../../README.md).

## Layout

```
apps/geo/
├── cmd/api/           # HTTP API entrypoint
├── cmd/importer/      # CSV → SQLite importer
├── cmd/inspect/       # Read-only database inspector
├── cmd/release/       # GitHub Releases metadata CLI
├── cmd/download/      # Release asset download + gzip extract
├── cmd/update/        # End-to-end catalog updater
├── internal/
│   ├── access/        # API keys, access tiers, rate limiting
│   ├── config/        # Environment configuration
│   ├── model/         # Country / Region / City models
│   ├── database/      # SQLite open / schema / integrity
│   ├── importer/      # Import pipeline
│   ├── inspect/       # Database inspection / readiness report
│   ├── release/       # GitHub Releases API client
│   ├── download/      # Asset download + gzip extraction
│   ├── update/        # Updater orchestrator
│   ├── repository/    # Read queries
│   ├── textutil/      # Name normalization for search
│   └── httpapi/       # HTTP handlers
├── docs/              # Cloud Run / ops notes
├── migrations/        # Schema reference SQL
├── data/              # Local CSV / DB (gitignored except .gitkeep)
└── testdata/          # Committed CSV fixtures
```

Build metadata lives in `internal/buildinfo` and is injected with `-ldflags` (`VERSION`, `GIT_COMMIT`, `BUILD_DATE`).

## Prerequisites

- Go 1.22+ (developed against the current stable toolchain)
- Optional: Docker, GNU Make

## Quick start

```bash
# Fetch dependencies
make tidy

# Import a cities CSV (place your download at ./data/cities.csv)
make import \
  INPUT=./data/cities.csv \
  DATASET_VERSION=v3.2-export.6 \
  DATASET_SHA256=abc123

# Inspect the generated database (read-only)
make inspect DATABASE=./data/geo.db

# Run the API
make run

# Or:
GEO_DATABASE_PATH=./data/geo.db go run ./cmd/api
```

Without Make:

```bash
go run ./cmd/importer \
  --input ./data/cities.csv \
  --output ./data/geo.db \
  --dataset-version v3.2-export.6 \
  --dataset-sha256 abc123

go run ./cmd/inspect --database ./data/geo.db

GEO_DATABASE_PATH=./data/geo.db go run ./cmd/api
```

## Make targets

| Target | Description |
|--------|-------------|
| `make tidy` | `go mod tidy` |
| `make fmt` | `go fmt ./...` |
| `make vet` | `go vet ./...` |
| `make test` | `go test ./...` |
| `make build` | Build API, importer, inspect, release, and download binaries |
| `make run` | Build and run the API against `OUTPUT` (default `./data/geo.db`) |
| `make import` | Import CSV → SQLite (`INPUT`, `OUTPUT`, `DATASET_VERSION`, `DATASET_SHA256`) |
| `make inspect` | Inspect a Geo database (`DATABASE`, default `./data/geo.db`) |
| `make inspect-json` | Same as inspect with `--json` |
| `make release` | Fetch latest GitHub release metadata (`OWNER`, `REPO`) |
| `make release-json` | Same as release with `--json` |
| `make download` | Download the default cities CSV gzip asset |
| `make download-json` | Same as download with `--json` |
| `make download-extract` | Download and extract gzip to `EXTRACT_OUTPUT` |
| `make update` | Refresh `DATABASE` from the latest GitHub release |
| `make force-update` | Same as update with `--force` |
| `make update-json` | Same as update with `--json` |
| `make docker-build` | Build the Cloud Run image (requires `data/geo.db`) |
| `make docker-run` | Run the image on port 8080 |
| `make clean` | Remove build artifacts |

Importer overrides:

```bash
make import \
  INPUT=./data/cities.csv \
  OUTPUT=./data/geo.db \
  DATASET_VERSION=v3.2-export.6 \
  DATASET_SHA256=abc123
```

Inspect overrides:

```bash
make inspect DATABASE=./data/geo.db
make inspect-json DATABASE=./data/geo.db
```

## Inspect command

`cmd/inspect` opens an existing Geo SQLite file **read-only** and prints an operational readiness report. It does not repair, migrate, vacuum, or otherwise modify the database.

```bash
go run ./cmd/inspect --database ./data/geo.db
go run ./cmd/inspect ./data/geo.db
go run ./cmd/inspect --database ./data/geo.db --json
go run ./cmd/inspect --database ./data/geo.db --strict
go run ./cmd/inspect --database ./data/geo.db --timeout 10s
```

Provide either `--database` or a positional path, not both. Default path: `./data/geo.db`.

### Checks performed

- SQLite `integrity_check` and `foreign_key_check`
- Required tables, columns, indexes, and metadata keys
- Metadata value rules (`generated_at` RFC3339, positive integer `schema_version`, 64-char hex `dataset_sha256`)
- Non-zero country / region / city counts
- Blank name/code sanity checks
- Orphan and city–region–country consistency checks
- IANA timezone validation for non-empty city timezones
- Warnings for unknown extras / future schema / newer generator (strict mode fails on warnings)

### Exit codes

| Code | Meaning |
|------|---------|
| `0` | All required checks passed (`READY`) |
| `1` | Database opened, but one or more checks failed (`NOT READY`) |
| `2` | Usage error, missing/unreadable database, timeout, or operational failure |

## GitHub Releases client

`internal/release` is a generic GitHub Releases API client. It retrieves release metadata only — it does not download assets, verify checksums, or run the importer.

Default CLI repository: `dr5hn/countries-states-cities-database`.

```bash
go run ./cmd/release
go run ./cmd/release --latest
go run ./cmd/release --tag v3.2-export.6
go run ./cmd/release --json --latest
go run ./cmd/release --owner golang --repo go --latest

make release
make release-json
make release OWNER=golang REPO=go
```

Optional authentication:

| Variable | Description |
|----------|-------------|
| `GITHUB_TOKEN` | When set, sent as `Authorization: Bearer <token>` to raise GitHub rate limits |

Unauthenticated requests still work. After each response the client stores `X-RateLimit-Remaining` and `X-RateLimit-Reset`, available via `Client.RateLimit()` and shown in CLI output.

This package is the first step toward an automatic updater: a future downloader will select a release asset from this metadata, then hand the file to importer + inspect before publishing a new Cloud Run revision.

## Asset download

`internal/download` streams a selected release asset to disk, verifies size/SHA-256, and can extract a single gzip stream. It does **not** import CSV or publish a database.

Default asset: `csv-cities.csv.gz`.

```bash
# Download only
go run ./cmd/download
make download

# Download + extract
go run ./cmd/download --extract
make download-extract

# Pin a release tag and custom paths
go run ./cmd/download \
  --tag v3.2-export.6 \
  --asset csv-cities.csv.gz \
  --output ./data/downloads/cities-v3.2-export.6.csv.gz \
  --extract \
  --extract-output ./data/downloads/cities-v3.2-export.6.csv

go run ./cmd/download --json
```

Validation:

- Expected size from GitHub asset metadata vs streamed bytes
- Optional `--sha256` for the downloaded gzip (64 hex chars)
- Always computes lowercase SHA-256 for download and extraction
- Extraction stops if decompressed output would exceed 512 MiB (`ErrExtractedSizeLimit`)
- Writes to unique temporary siblings, then atomically replaces the destination
- Existing destinations are preserved when validation fails

Optional `GITHUB_TOKEN` is forwarded on download requests (and release lookup). Artifacts under `data/downloads/` are gitignored.

### Download exit codes

| Code | Meaning |
|------|---------|
| `0` | Download (and optional extraction) succeeded |
| `1` | Validation failure (size/checksum/gzip/limit/asset selection) |
| `2` | Usage or operational failure (flags, network, permissions, timeout) |

## Catalog updater

`cmd/update` orchestrates existing packages into one pipeline:

1. Read current `dataset_version` from the production database (if present)
2. Query GitHub Releases (`latest` or `--tag`)
3. Exit successfully when already current (unless `--force`)
4. Download `csv-cities.csv.gz` into a workspace
5. Extract gzip → CSV
6. Import → candidate SQLite
7. Inspect with `--strict`
8. Atomically publish only when `ready`

```bash
go run ./cmd/update
go run ./cmd/update --force
go run ./cmd/update --json
go run ./cmd/update --keep-workspace

make update
make force-update
make update-json
```

Defaults: workspace `./data/work`, database `./data/geo.db`, lock `./data/update.lock`, timeout `10m`.

Behavior:

- Production `geo.db` is never overwritten before strict inspection succeeds
- Publish stages `geo.db.new` then renames over `geo.db`
- Concurrent runs are blocked by an exclusive lock file
- Workspace is deleted after success or failure unless `--keep-workspace`
- Importer metadata uses the release tag and the downloaded asset SHA-256 (plus shared model provider/generator/schema constants)

### Update exit codes

| Code | Meaning |
|------|---------|
| `0` | Updated successfully, or already up to date |
| `1` | Validation/inspection failure |
| `2` | Usage, lock contention, network/GitHub, permissions, timeout |

## API

| Method | Path | Notes |
|--------|------|-------|
| `GET` | `/health` | Liveness (no auth, no DB). Includes `status`, `version`, optional `environment` |
| `GET` | `/version` | **Service** build metadata (`version`, `gitCommit`, `buildDate`, `goVersion`) |
| `GET` | `/v1/version` | **Dataset** catalog metadata (provider / schema / SHA) — public contract unchanged |
| `GET` | `/v1/countries` | All countries |
| `GET` | `/v1/countries/search?q=&limit=` | Accent-insensitive country name search |
| `GET` | `/v1/countries/{code}` | Country by ISO code (case-insensitive) |
| `GET` | `/v1/countries/{code}/regions` | Regions for country |
| `GET` | `/v1/regions/search?q=&limit=` | Accent-insensitive region name search |
| `GET` | `/v1/regions/{id}` | Region by id |
| `GET` | `/v1/regions/{id}/cities` | Cities in region |
| `GET` | `/v1/cities/{id}` | City detail with nested region/country |
| `GET` | `/v1/cities/search?q=&limit=` | Accent-insensitive city name search (`q` ≥ 2 chars; default limit 20, max 100) |

All JSON responses use camelCase. Errors:

```json
{
  "error": {
    "code": "city_not_found",
    "message": "City not found."
  }
}
```

Request IDs: send `X-Request-ID` or receive a generated value on every response.

### Access control and rate limiting

The API is public by default. Every protected route passes through `internal/access`, which:

1. authenticates optional API keys
2. classifies the client as **public**, **trusted**, or **internal**
3. applies a per-client token-bucket rate limit
4. returns standard rate-limit headers

#### Access levels

| Level | How identified | Default limit | Burst |
|-------|----------------|---------------|-------|
| Public | no API key; limited by client IP | 60 req/min | 15 |
| Trusted | configured trusted API key | 300 req/min | 60 |
| Internal | configured internal API key (Finba backend) | 10000 req/min | 500 |

Limits are token buckets (`golang.org/x/time/rate`). A setting of 60 requests/minute refills at **1 token/second**, not 60 tokens/second.

Internal access is intentionally finite: it protects the service from accidental loops and bugs in the Finba backend while remaining high enough for normal server-to-server traffic.

#### Authentication

Supported headers (Bearer wins if both are present):

1. `Authorization: Bearer <key>` (scheme is case-insensitive)
2. `X-API-Key: <key>`

Behavior:

- no key → Public
- valid trusted/internal key → that tier
- any supplied but invalid or malformed credential → **HTTP 401** (never silently downgraded to Public)

#### Examples

Anonymous:

```bash
curl "http://localhost:8080/v1/cities/search?q=tramandai"
```

Trusted:

```bash
curl \
  -H "Authorization: Bearer $TRUSTED_KEY" \
  "http://localhost:8080/v1/cities/search?q=tramandai"
```

Internal (server-to-server only — never embed in browser JavaScript):

```bash
curl \
  -H "X-API-Key: $GEO_INTERNAL_API_KEY" \
  "http://localhost:8080/v1/countries"
```

Invalid key:

```bash
curl \
  -H "Authorization: Bearer invalid" \
  "http://localhost:8080/v1/countries"
```

Expected: HTTP 401.

#### Rate-limit headers

Protected responses include:

- `RateLimit-Limit` — configured requests/minute for the selected tier
- `RateLimit-Remaining` — approximate available tokens (floored, never negative)
- `RateLimit-Reset` — seconds until another token is expected (token-bucket timing, not a fixed calendar window)

On HTTP 429:

- `Retry-After` (integer seconds, minimum 1)
- JSON `error.retryAfter` matching that value

`GET /health`, `GET /version`, and `GET /v1/version` bypass authentication and rate limiting.

#### Proxy headers

`GEO_TRUST_PROXY_HEADERS` defaults to `false`. When false, only `RemoteAddr` is used for public rate-limit buckets.

When true, the API trusts client-supplied `CF-Connecting-IP` and `X-Forwarded-For` (see precedence under Access control). The application does **not** verify that the immediate TCP peer is Cloudflare.

This is a **deployment concern**, not an application bug: trusting forward headers is correct only when the network topology guarantees those headers come from a reverse proxy you control. Baking `true` into the container image would force that assumption on every environment, including a first Cloud Run revision whose `*.run.app` URL is still publicly reachable.

Enable `GEO_TRUST_PROXY_HEADERS=true` only when **all** of the following hold:

1. Every request reaches the application through a trusted reverse proxy (for example Cloudflare → Cloud Run).
2. Direct access to the Cloud Run `*.run.app` URL is prevented or is otherwise not a practical bypass of that proxy.
3. Proxy headers are therefore considered trustworthy (clients cannot inject `CF-Connecting-IP` / `X-Forwarded-For` on an open path).

Until then, leave the variable unset or `false` so public limiting uses the real peer address.

Set the flag explicitly on the Cloud Run service (or deploy flags) when the topology is ready — never in the Dockerfile.

#### Multi-instance limitation

The in-memory limiter is **per API process / Cloud Run instance**. It is not a globally exact distributed quota. With multiple instances, a client may see an effective aggregate limit higher than the configured per-instance value.

This still provides process protection, burst control, and accidental-loop protection. Future hardening may add edge (Cloudflare) limits or a Redis-backed global quota.

#### Intended Finba production flow

```
Browser → Finba Laravel backend → Geo API (internal API key)
```

The browser must never receive or expose the internal key.

### Accent-insensitive search

Country, region, and city search use an internal `normalized_name` column built at import time.

Normalization (`textutil.NormalizeSearch`):

1. trim whitespace
2. lowercase
3. Unicode NFD
4. strip combining marks (diacritics)
5. collapse repeated whitespace

Examples that match the same entity:

- `tramandai` ↔ `Tramandaí`
- `sao paulo` ↔ `São Paulo`
- `rio grande` ↔ `Rio Grande do Sul`

Browser examples (local API):

- http://localhost:8080/v1/cities/search?q=tramandai
- http://localhost:8080/v1/cities/search?q=sao%20paulo
- http://localhost:8080/v1/regions/search?q=rio%20grande
- http://localhost:8080/v1/countries/search?q=brazil

Public JSON always returns the original accented `name`. `normalized_name` is never exposed.

After schema changes, rebuild the catalog (`make force-update` / `go run ./cmd/update --force`). Current schema version: **2**.

## Configuration

Environment variables fall into two groups:

1. **API runtime** (`cmd/api` / Cloud Run) — listen port, database path, access tiers, proxy trust
2. **Catalog tooling** (`cmd/release`, `cmd/download`, `cmd/update`) — optional GitHub auth while building `geo.db`

Cloud Run must never receive catalog-tooling secrets. The production image already contains a baked `geo.db`; the API process does not call GitHub.

### First-deploy secrets checklist

| Variable | Required on Cloud Run? | Store as | Notes |
|----------|------------------------|----------|-------|
| `GEO_INTERNAL_API_KEY` | **Strongly recommended** (required for Finba Laravel) | **Secret Manager** | Same value on Geo and Laravel (`apps/web` `GEO_INTERNAL_API_KEY`) |
| `GEO_TRUSTED_API_KEYS` | Optional (empty OK) | **Secret Manager** | Comma-separated; create secret even if empty so deploy `--update-secrets` succeeds |
| `GEO_TRUST_PROXY_HEADERS` | Leave `false` until proxy path is locked down | Normal env var | Default `false`; enable only per checklist below — never bake into the image |
| `GITHUB_TOKEN` | **No — do not mount on Cloud Run** | N/A at runtime | Only for local/CI catalog tooling; Actions uses the built-in workflow token during deploy jobs |

Generate API keys offline (cryptographically random). There is no committed key generator beyond this recipe:

```bash
# Internal key (single value) — 32 bytes → ~43 chars base64
openssl rand -base64 32

# Trusted key (repeat per partner / automation client)
openssl rand -base64 32

# Alternative (hex)
openssl rand -hex 32

# Alternative (pwgen, if installed)
pwgen -s 48 1
```

Or via Make from `apps/geo`:

```bash
make gen-api-key
```

Recommended: at least **32 bytes** of entropy (e.g. `openssl rand -base64 32`). Do not use dictionary words, short strings, or the examples below in production.

Example **non-secret** placeholders (never reuse as real values):

```text
GEO_INTERNAL_API_KEY=replace-me-with-openssl-rand-base64-32
GEO_TRUSTED_API_KEYS=partner-a-key,partner-b-key
GEO_TRUST_PROXY_HEADERS=false
```

Store the internal key in Secret Manager, then configure Laravel with the **same** value (also as a secret on the web service). Never put either key in browser JS, public repos, or GitHub Actions secrets when Secret Manager is available.

### API runtime variables

| Variable | Required | Default | Cloud Run | Description |
|----------|----------|---------|-----------|-------------|
| `PORT` | Yes (Cloud Run injects) | `8080` | Env | Listen port |
| `GEO_ENV` | No | `development` | Env → `production` | `development`, `staging`, `production`, or `test` |
| `GEO_DATABASE_PATH` | No | `./data/geo.db` | Env → `/app/data/geo.db` | SQLite catalog path (baked into the image) |
| `LOG_LEVEL` | No | `info` | Env | `debug`, `info`, `warn`, `error` |
| `HTTP_READ_TIMEOUT` | No | `5s` | Env (optional) | Go duration or integer seconds |
| `HTTP_READ_HEADER_TIMEOUT` | No | `5s` | Env (optional) | Go duration or integer seconds |
| `HTTP_WRITE_TIMEOUT` | No | `10s` | Env (optional) | Go duration or integer seconds |
| `HTTP_IDLE_TIMEOUT` | No | `60s` | Env (optional) | Go duration or integer seconds |
| `HTTP_SHUTDOWN_TIMEOUT` | No | `10s` | Env (optional) | Graceful shutdown deadline |
| `GEO_PUBLIC_RATE_LIMIT_PER_MINUTE` | No | `60` | Env (optional) | Public token-bucket refill (req/min) |
| `GEO_PUBLIC_RATE_LIMIT_BURST` | No | `15` | Env (optional) | Public burst |
| `GEO_TRUSTED_RATE_LIMIT_PER_MINUTE` | No | `300` | Env (optional) | Trusted req/min |
| `GEO_TRUSTED_RATE_LIMIT_BURST` | No | `60` | Env (optional) | Trusted burst |
| `GEO_INTERNAL_RATE_LIMIT_PER_MINUTE` | No | `10000` | Env (optional) | Internal req/min |
| `GEO_INTERNAL_RATE_LIMIT_BURST` | No | `500` | Env (optional) | Internal burst |
| `GEO_INTERNAL_API_KEY` | Recommended in prod | _(empty)_ | **Secret** | Single Finba backend key → **internal** tier |
| `GEO_TRUSTED_API_KEYS` | No | _(empty)_ | **Secret** | Comma-separated keys → **trusted** tier |
| `GEO_ACCESS_CLIENT_TTL` | No | `30m` | Env (optional) | Inactive limiter bucket TTL |
| `GEO_ACCESS_CLEANUP_INTERVAL` | No | `5m` | Env (optional) | Limiter cleanup interval |
| `GEO_TRUST_PROXY_HEADERS` | No (default safe) | `false` | Env (opt-in later) | Trust `CF-Connecting-IP` / `X-Forwarded-For` for public IP buckets — see Proxy headers |

### Catalog tooling only (not Cloud Run)

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `GITHUB_TOKEN` | No | _(empty)_ | Optional Bearer token for GitHub Releases API / asset download (`cmd/release`, `cmd/download`, `cmd/update`). Raises rate limits. Unauthenticated GitHub access still works with lower limits. |

`GITHUB_TOKEN` is read via `os.Getenv` inside the release/download clients so local and CI catalog builds can opt in without changing flags. The API binary (`cmd/api`) does **not** read it. Do **not** add it to Cloud Run env or Secret Manager for the Geo service.

There is no CORS configuration: the API is server-to-server only (Finba Laravel → Geo). Do not call it from browsers with the internal key.

### Access-key design (why two tiers?)

**No data endpoint requires an API key.** Catalog routes (`/v1/countries`, `/v1/cities/search`, …) work anonymously at the **public** tier (IP-limited). Keys only raise the rate-limit tier:

| Tier | Env | Who | Default limit | Purpose |
|------|-----|-----|---------------|---------|
| Public | _(none)_ | Anonymous internet / misconfigured clients | 60/min | Abuse protection by client IP |
| Trusted | `GEO_TRUSTED_API_KEYS` | Optional partners, scripts, non-Finba automation | 300/min | Higher quota without Finba’s internal key |
| Internal | `GEO_INTERNAL_API_KEY` | Finba Laravel only | 10000/min | Server-to-server traffic; still capped to stop accidental loops |

Excluded from auth/rate limits: `GET /health`, `GET /version`, `GET /v1/version`.

If `GEO_INTERNAL_API_KEY` is missing: the API still starts; Finba without a key is treated as **public** (stricter limits). If Finba sends a key that does not match, the API returns **401**.

If `GEO_TRUSTED_API_KEYS` is missing/empty: no trusted tier; only public + (optional) internal.

If `GEO_TRUST_PROXY_HEADERS` is missing/`false`: public rate limits use `RemoteAddr`. That is the correct default for a direct Cloud Run (`*.run.app`) deployment. Enabling `true` too early allows header spoofing; enabling it after a trusted proxy path is locked down improves per-client fairness when Cloudflare (or similar) sits in front.

## Docker / Cloud Run

Runtime image: **distroless static nonroot**. SQLite uses `modernc.org/sqlite` (pure Go), so the binary is built with `CGO_ENABLED=0` and needs no libc.

### Local Docker

```bash
# Requires data/geo.db (make update / make import)
make docker-build
make docker-run

# Manual:
docker build \
  --build-arg VERSION=0.1.0 \
  --build-arg GIT_COMMIT=$(git rev-parse HEAD) \
  --build-arg BUILD_DATE=$(date -u +%Y-%m-%dT%H:%M:%SZ) \
  -t finba-geo:local .

docker run --rm -p 8080:8080 \
  -e PORT=8080 \
  -e GEO_ENV=development \
  -e GEO_INTERNAL_API_KEY=dev-internal-key \
  finba-geo:local

curl -s http://localhost:8080/health
curl -s http://localhost:8080/version
curl -s http://localhost:8080/v1/version
```

The image is immutable: the database is baked in at build time. No CSV download happens at container startup. Never bake API keys into the image.

### Production

Full bootstrap and operations guide: **[docs/cloudrun.md](docs/cloudrun.md)** (Workload Identity Federation, Artifact Registry, Secret Manager, Cloud Run sizing, `geo.finba.se` / Cloudflare, monitoring, alerts, rollback, smoke tests).

After one-time GCP / GitHub / Cloudflare setup, production deploys with:

```bash
git push origin main
```

GitHub Actions (monorepo root):

| Workflow | When | Purpose |
|----------|------|---------|
| `geo-ci.yml` | PR / push on `apps/geo/**` | `gofmt`, `go vet`, `go test` |
| `geo-deploy.yml` | `main` push on `apps/geo/**`, or `workflow_dispatch` | test → catalog → OIDC → Artifact Registry → Cloud Run → smoke |

Deploy authenticates with **Workload Identity Federation (OIDC)** — no service-account JSON keys.

GitHub Environment `production` bootstrap secrets only:

`GCP_PROJECT_ID`, `GCP_REGION`, `ARTIFACT_REGISTRY_REPO`, `CLOUD_RUN_SERVICE`, `GCP_WORKLOAD_IDENTITY_PROVIDER`, `GCP_SERVICE_ACCOUNT`

Runtime keys (`GEO_INTERNAL_API_KEY`, `GEO_TRUSTED_API_KEYS`) live in **Secret Manager** and are mounted by Cloud Run. Smoke tests read the internal key from Secret Manager at deploy time.

Recommended production shape: 1 CPU, 512Mi, concurrency 80, min 1 / max 5 instances, 30s timeout, unauthenticated ingress (app-layer keys), revision labels `service` / `environment` / `commit` / `version`.

Public hostname: **geo.finba.se** (Cloudflare → Cloud Run).

## Catalog notes

- Supplier CSV “states” are **regions** in Finba (API and schema).
- Supplier integer IDs are reused as primary keys.
- Latitude, population, flags, translations, and other unused supplier fields are discarded.

## Tests

```bash
make test
```

Fixtures live under `testdata/`. The real supplier CSV and production `geo.db` are gitignored.
