# Geo service client

Laravel client for the Finba Geo Go API (`apps/geo`).

Namespace: `App\Support\Geo`

**The Geo API is the sole source of truth for countries, regions, and cities.**

Business code uses `Geo` / `GeoContract` — never `Http` directly. Finba does not maintain a local geographic catalog, `cities` table, or Sushi country/region datasets.

## Configuration

```env
GEO_BASE_URL=http://127.0.0.1:8080
GEO_INTERNAL_API_KEY=
GEO_TIMEOUT=5
GEO_CONNECT_TIMEOUT=2
GEO_RETRY_ATTEMPTS=2
GEO_RETRY_DELAY=100
GEO_CACHE=true
GEO_CACHE_COUNTRIES_TTL=86400
GEO_CACHE_COUNTRY_TTL=86400
GEO_CACHE_REGIONS_TTL=86400
GEO_CACHE_REGION_TTL=86400
GEO_CACHE_CITY_TTL=86400
```

`GEO_INTERNAL_API_KEY` is server-side only. It must never appear in browser responses, Livewire payloads, or public config.

All HTTP calls go through `App\Support\Geo\GeoClient`, which reads `GEO_BASE_URL` and sends `X-API-Key: {GEO_INTERNAL_API_KEY}` when the key is configured (internal rate-limit tier on the Go API). Do not call `Http` against the Geo host from controllers or other services.

## Meaning of `geo_city_id`

On `users`, `transactions`, and `people`:

- `geo_city_id` is an **unsigned bigint** storing the **external Geo API city integer ID**
- There is **no database foreign key** (authoritative rows live in `apps/geo`)
- Never store a local UUID in `geo_city_id`

Display:

```php
Geo::city($model->geo_city_id); // CityDetail with nested region + country
app(GeoPresenter::class)->fullLabel($model->geo_city_id);
```

Country and region are **not** duplicated on domain tables unless a future querying requirement is explicitly documented.

## Forms

Reusable fields: `App\Support\Geo\Support\GeoFields`

Cascade:

1. `geo_country_code` (form state)
2. `geo_region_id` (form state)
3. `geo_city_id` (form state + persisted)

Changing country clears region and city. Changing region clears city.

Used by profile, onboarding, transactions, and people.

Write validation: `GeoCityResolver::resolveForPersistence()`.

## City search limitation

City options load from `GET /v1/regions/{id}/cities`, then Filament searches locally within that list.

Global `GET /v1/cities/search` is **not** used for selectors because the Go API has no `regionId` filter yet — a limited global search can omit valid cities in the selected region.

### Future Geo endpoints (recommended)

```http
GET /v1/cities/search?q={query}&regionId={id}
GET /v1/regions/{id}/cities/search?q={query}
GET /v1/cities?ids=1001,1002,1003
```

Do not implement these in the Laravel app; they belong in `apps/geo`.

## Caching

Stable lookups use versioned keys (`geo:v1:...`) and long TTLs (default 24h):

| Lookup | Key |
|--------|-----|
| countries | `geo:v1:countries` |
| country | `geo:v1:country:{CODE}` |
| regions | `geo:v1:regions:{CODE}` |
| region | `geo:v1:region:{id}` |
| city | `geo:v1:city:{id}` |

Implementation uses `Cache::flexible()` (fresh TTL + 2× stale window) when the cache driver supports it.

Search endpoints are never persisted to cache.

`Geo::cities($regionId)` is **request-memoized only** (not written to the cache store).

`GeoManager` is a **scoped** binding so request memoization does not grow across queue workers.

Invalidate by flushing keys with prefix `geo:v1:` or clearing the configured cache store. If `CityDetail` mapping changes incompatibly, increment the `geo:v1` namespace in `GeoManager`.

## Availability

When Geo is temporarily unavailable:

1. Cached city/country/region details remain usable when present
2. Interactive selectors show a retryable helper message
3. Saved `geo_city_id` values are **never** cleared automatically
4. Labels may show “Location temporarily unavailable”
5. A Geo 404 shows “Location no longer available” without remapping by name
6. Internal credentials and raw HTTP bodies are never exposed

## Timezone boundary

`users.timezone` stores an IANA identifier (e.g. `America/Sao_Paulo`).

When the user selects a city and `users.timezone` is empty, Finba suggests the timezone from `CityDetail::timezone`.

- Application and database timezone remain UTC
- Do not store UTC offsets such as `-03:00`
- Full presentation formatting is a separate task

## Currency (non-geographic)

`resources/data/country-currencies.php` maps ISO country codes to currencies for `MoneyFormatter`. This is **not** a geographic catalog.

## Real API contract (Go)

All successful JSON bodies are **bare** camelCase arrays/objects. There is **no** `{ "data": ... }` envelope.

| Endpoint | Shape |
|----------|--------|
| `GET /v1/countries` | `Country[]` |
| `GET /v1/countries/{code}` | `Country` |
| `GET /v1/countries/search?q=&limit=` | `Country[]` |
| `GET /v1/countries/{code}/regions` | `Region[]` |
| `GET /v1/regions/{id}` | `Region` |
| `GET /v1/regions/search?q=&limit=` | `Region[]` |
| `GET /v1/regions/{id}/cities` | `City[]` |
| `GET /v1/cities/{id}` | `CityDetail` |
| `GET /v1/cities/search?q=&limit=` | `City[]` |

### CityDetail (`GET /v1/cities/{id}`)

```json
{
  "id": 1001,
  "name": "Tramandaí",
  "timezone": "America/Sao_Paulo",
  "region": { "id": 2021, "code": "RS", "name": "Rio Grande do Sul" },
  "country": { "id": 31, "code": "BR", "name": "Brazil" }
}
```

## Package layout

```
app/Support/Geo/
├── Contracts/GeoContract.php
├── DTO/
├── Exceptions/
├── Facades/Geo.php
├── Providers/GeoServiceProvider.php
├── Support/GeoFields.php
├── Support/GeoPresenter.php
├── Support/GeoCityResolver.php
├── GeoClient.php
└── GeoManager.php
```

## N+1 / tables

Dense tables that show city labels for many rows should:

1. Resolve only visible paginated rows
2. Rely on persistent city-detail cache + request memoization
3. Prefer filters built from distinct `geo_city_id` values already on the user’s records

A batch detail endpoint (`GET /v1/cities?ids=…`) is the long-term fix.
