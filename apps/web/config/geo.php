<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Geo Service Base URL
    |--------------------------------------------------------------------------
    |
    | Root URL of the Go Geo API (without trailing slash). Requests are made
    | against /v1/... paths under this host.
    |
    */

    'base_url' => rtrim(env('GEO_BASE_URL', 'http://127.0.0.1:8080'), '/'),

    /*
    |--------------------------------------------------------------------------
    | Internal API Key
    |--------------------------------------------------------------------------
    |
    | Sent as X-API-Key on every GeoClient request. Never expose to the browser.
    |
    */

    'internal_api_key' => env('GEO_INTERNAL_API_KEY'),

    'timeout' => (int) env('GEO_TIMEOUT', 5),

    'connect_timeout' => (int) env('GEO_CONNECT_TIMEOUT', 2),

    'retry_attempts' => (int) env('GEO_RETRY_ATTEMPTS', 2),

    'retry_delay' => (int) env('GEO_RETRY_DELAY', 100),

    'user_agent' => env('GEO_USER_AGENT', 'Finba.se Geo Client'),

    /*
    |--------------------------------------------------------------------------
    | Caching
    |--------------------------------------------------------------------------
    |
    | Cache stable catalog lookups. Search endpoints are never cached.
    | Region city lists are request-memoized only (not persisted).
    |
    | Keys use the geo:v2: namespace (JSON-safe arrays only). Increment in GeoManager
    | when the cached payload shape changes incompatibly.
    |
    */

    'cache' => (bool) env('GEO_CACHE', true),

    'cache_store' => env('GEO_CACHE_STORE'),

    /*
    | Fresh TTL (seconds). Stale window is 2× fresh via Cache::flexible().
    */
    'cache_ttl' => [
        'countries' => (int) env('GEO_CACHE_COUNTRIES_TTL', 86400),
        'country' => (int) env('GEO_CACHE_COUNTRY_TTL', 86400),
        'regions' => (int) env('GEO_CACHE_REGIONS_TTL', 86400),
        'region' => (int) env('GEO_CACHE_REGION_TTL', 86400),
        'city' => (int) env('GEO_CACHE_CITY_TTL', 86400),
    ],

];
