<?php

use App\Support\Geo\Contracts\GeoContract;
use App\Support\Geo\DTO\City;
use App\Support\Geo\DTO\CityDetail;
use App\Support\Geo\DTO\Country;
use App\Support\Geo\Exceptions\GeoAuthenticationException;
use App\Support\Geo\Exceptions\GeoNotFoundException;
use App\Support\Geo\Exceptions\GeoRateLimitException;
use App\Support\Geo\Exceptions\GeoResponseException;
use App\Support\Geo\Facades\Geo;
use App\Support\Geo\GeoManager;
use App\Support\Geo\Support\GeoFields;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    prepareGeoTestEnvironment();
    Cache::flush();
});

it('resolves facade and dependency injection under App\\Support\\Geo', function () {
    fakeGeoContractApi();

    expect(app(GeoContract::class))->toBeInstanceOf(GeoManager::class)
        ->and(app('geo'))->toBeInstanceOf(GeoManager::class)
        ->and(Geo::countries()->first())->toBeInstanceOf(Country::class);
});

it('sends bearer authentication headers', function () {
    fakeGeoContractApi();

    Geo::countries();

    Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer test-geo-key')
        && $request->hasHeader('Accept', 'application/json')
        && str_ends_with($request->url(), '/v1/countries'));
});

it('hydrates DTOs from real Go camelCase contract fixtures', function () {
    fakeGeoContractApi();

    $country = Geo::country('BR');
    $region = Geo::region(2021);
    $cities = Geo::cities(2021);
    $detail = Geo::city(1001);

    expect($country->id)->toBe(31)
        ->and($country->code)->toBe('BR')
        ->and($region->countryId)->toBe(31)
        ->and($region->code)->toBe('RS')
        ->and($cities->first())->toBeInstanceOf(City::class)
        ->and($cities->first()->regionId)->toBe(2021)
        ->and($detail)->toBeInstanceOf(CityDetail::class)
        ->and($detail->name)->toBe('Tramandaí')
        ->and($detail->timezone)->toBe('America/Sao_Paulo')
        ->and($detail->country->code)->toBe('BR')
        ->and($detail->region?->code)->toBe('RS');
});

it('rejects unexpected data envelopes with GeoResponseException', function () {
    Http::fake([
        'geo.test/v1/countries' => Http::response(['data' => geoFixture('countries')]),
    ]);

    expect(fn () => Geo::countries())->toThrow(GeoResponseException::class);
});

it('uses versioned cache keys for non-search lookups', function () {
    fakeGeoContractApi();

    Geo::countries();
    Geo::countries();
    Geo::country('BR');
    Geo::city(1001);
    Geo::city(1001);

    expect(Cache::has('geo:v1:countries'))->toBeTrue()
        ->and(Cache::has('geo:v1:country:BR'))->toBeTrue()
        ->and(Cache::has('geo:v1:city:1001'))->toBeTrue();

    Http::assertSentCount(3);
});

it('does not persist region city lists in cache, but memoizes within the request', function () {
    fakeGeoContractApi();

    Geo::searchCities('tram');
    Geo::searchCities('tram');
    Geo::cities(2021);
    Geo::cities(2021);

    // Two uncached searches + one region city list (second cities() is request-memoized).
    Http::assertSentCount(3);
    expect(Cache::has('geo:v1:cities:2021'))->toBeFalse();
});

it('maps 404 to GeoNotFoundException', function () {
    Http::fake([
        'geo.test/v1/cities/9' => Http::response(geoFixture('error_city_not_found'), 404),
    ]);

    expect(fn () => Geo::city(9))->toThrow(GeoNotFoundException::class);
});

it('maps 401 to GeoAuthenticationException', function () {
    Http::fake([
        'geo.test/v1/countries' => Http::response([
            'error' => ['code' => 'invalid_api_key', 'message' => 'Invalid API credentials.'],
        ], 401),
    ]);

    expect(fn () => Geo::countries())->toThrow(GeoAuthenticationException::class);
});

it('maps 429 and parses Retry-After', function () {
    Http::fake([
        'geo.test/v1/countries' => Http::response(
            geoFixture('error_rate_limit'),
            429,
            ['Retry-After' => '3'],
        ),
    ]);

    try {
        Geo::countries();
        $this->fail('Expected GeoRateLimitException');
    } catch (GeoRateLimitException $exception) {
        expect($exception->retryAfter)->toBe(3)
            ->and($exception->status)->toBe(429);
    }
});

it('builds GeoFields with geo_ prefixed names', function () {
    fakeGeoContractApi();

    expect(GeoFields::country()->getName())->toBe('geo_country_code')
        ->and(GeoFields::region()->getName())->toBe('geo_region_id')
        ->and(GeoFields::city()->getName())->toBe('geo_city_id');
});

it('resets dependent geo fields when country or region changes', function () {
    $state = [
        'geo_country_code' => 'BR',
        'geo_region_id' => 2021,
        'geo_city_id' => 1001,
    ];

    $set = mock(Set::class);
    $set->shouldReceive('__invoke')->andReturnUsing(function (string $key, mixed $value = null) use (&$state) {
        $state[$key] = $value;
    });

    foreach ((new ReflectionProperty(GeoFields::country(), 'afterStateUpdated'))->getValue(GeoFields::country()) as $callback) {
        $callback($set);
    }

    expect($state['geo_region_id'])->toBeNull()
        ->and($state['geo_city_id'])->toBeNull();

    $state['geo_city_id'] = 1001;

    foreach ((new ReflectionProperty(GeoFields::region(), 'afterStateUpdated'))->getValue(GeoFields::region()) as $callback) {
        $callback($set);
    }

    expect($state['geo_city_id'])->toBeNull();
});

it('loads region-scoped city options instead of global search', function () {
    fakeGeoContractApi();

    $options = Geo::cities(2021)
        ->mapWithKeys(fn (City $city): array => [$city->id => $city->name])
        ->all();

    expect($options)->toHaveKey(1001)
        ->and($options[1001])->toBe('Tramandaí')
        ->and($options)->toHaveKey(1002)
        ->and($options[1002])->toBe('Porto Alegre');
});
