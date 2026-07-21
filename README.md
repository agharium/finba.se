# Finba.se

Monorepo for Finba — personal finance (web) plus the geographic catalog API (geo).

## Layout

```text
finba.se/
├── .github/workflows/     # CI/CD (path-filtered per app)
├── apps/
│   ├── web/               # Laravel + Filament app → app.finba.se
│   └── geo/               # Go Geo API → geo.finba.se
├── LICENSE
└── README.md              # this file
```

| App | Path | Stack | Production host |
|-----|------|-------|-----------------|
| Web | [`apps/web`](apps/web) | PHP 8.4, Laravel, Filament | https://app.finba.se |
| Geo | [`apps/geo`](apps/geo) | Go, SQLite, Cloud Run | https://geo.finba.se |

Each app is independent: its own dependencies, Dockerfile, tests, and docs. Work **inside** the app directory (or use scripts that resolve their own app root).

## Documentation

- [Project changelog](https://app.finba.se/changelog) — public product and platform history
- [Web app docs](apps/web/README.md) · [Geo API docs](apps/geo/README.md)

## Quick start

### Web (`apps/web`)

```bash
cd apps/web
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run build
composer dev
```

Tests: `php artisan test --compact`  
Docs: [`apps/web/README.md`](apps/web/README.md), [`apps/web/docs/`](apps/web/docs/)

### Geo (`apps/geo`)

```bash
cd apps/geo
go mod tidy
go test ./...
# optional: make update && go run ./cmd/api
```

Docs: [`apps/geo/README.md`](apps/geo/README.md), [`apps/geo/docs/cloudrun.md`](apps/geo/docs/cloudrun.md)

## CI / CD

Workflows live at the repository root and use path filters:

| Workflow | Paths | Purpose |
|----------|-------|---------|
| [`web-ci.yml`](.github/workflows/web-ci.yml) | `apps/web/**` | Composer / Pint / Pest (PR and non-main pushes) |
| [`geo-ci.yml`](.github/workflows/geo-ci.yml) | `apps/geo/**` | `gofmt` / `vet` / `go test` |
| [`geo-deploy.yml`](.github/workflows/geo-deploy.yml) | `apps/geo/**` on `main` | OIDC → Artifact Registry → Cloud Run + smoke |


Web production deploy remains script-driven from `apps/web` (see [`apps/web/docs/deployment.md`](apps/web/docs/deployment.md)). Geo production deploy is GitHub Actions on `main`.

## Docker

Build contexts are **per app** (never the monorepo root):

```bash
# Web
cd apps/web && docker build -t finba-web:local .

# Geo (requires data/geo.db — see apps/geo README)
cd apps/geo && docker build -t finba-geo:local .
```

## Relationship

- **Geo** is the sole geographic source of truth (countries, regions, cities, timezones).
- **Web** calls Geo over HTTP (`App\Support\Geo`); it does not own the catalog schema.

Do not change `/v1/*` Geo response contracts without coordinating the Laravel client.
