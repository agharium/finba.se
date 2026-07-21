<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

use App\Support\Geo\Contracts\GeoContract;
use App\Support\Geo\GeoClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

function geoFixture(string $name): array
{
    return json_decode(
        file_get_contents(base_path("tests/Feature/Geo/Fixtures/{$name}.json")),
        true,
        flags: JSON_THROW_ON_ERROR,
    );
}

function fakeGeoContractApi(): void
{
    Http::fake([
        'geo.test/v1/countries' => Http::response(geoFixture('countries')),
        'geo.test/v1/countries/BR' => Http::response(geoFixture('country_br')),
        'geo.test/v1/countries/search*' => Http::response([geoFixture('country_br')]),
        'geo.test/v1/countries/BR/regions' => Http::response(geoFixture('regions_br')),
        'geo.test/v1/regions/2021' => Http::response(geoFixture('region_rs')),
        'geo.test/v1/regions/search*' => Http::response([geoFixture('region_rs')]),
        'geo.test/v1/regions/2021/cities' => Http::response(geoFixture('cities_rs')),
        'geo.test/v1/regions/2022/cities' => Http::response([]),
        'geo.test/v1/cities/1001' => Http::response(geoFixture('city_tramandai_detail')),
        'geo.test/v1/cities/search*' => Http::response(geoFixture('cities_search_tram')),
    ]);
}

function prepareGeoTestEnvironment(): void
{
    Cache::flush();

    config([
        'geo.base_url' => 'https://geo.test',
        'geo.internal_api_key' => 'test-geo-key',
        'geo.timeout' => 5,
        'geo.connect_timeout' => 2,
        'geo.retry_attempts' => 2,
        'geo.retry_delay' => 1,
        'geo.user_agent' => 'Finba Geo Test',
        'geo.cache' => true,
        'geo.cache_ttl' => [
            'countries' => 86400,
            'country' => 86400,
            'regions' => 86400,
            'region' => 86400,
            'city' => 86400,
        ],
        'geo.cache_store' => null,
    ]);

    app()->forgetInstance(GeoClient::class);
    app()->forgetInstance(GeoContract::class);
    app()->forgetScopedInstances();

    Http::preventStrayRequests();
}
