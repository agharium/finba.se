<?php

use App\Services\LocationCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

it('loads country options from the catalog', function () {
    $options = app(LocationCatalogService::class)->countryOptions();

    expect($options)->toHaveKey('BR')
        ->and($options['BR'])->toBe('Brasil');
});

it('loads brazilian region options for a country', function () {
    $options = app(LocationCatalogService::class)->regionOptions('BR');

    expect($options)->toHaveKey('RS')
        ->and($options['RS'])->toBe('Rio Grande do Sul');
});

it('searches RS catalog cities case and accent insensitively', function () {
    $matches = app(LocationCatalogService::class)->searchBrazilianCities('BR', 'RS', 'porto alegre');

    expect($matches)->toContain('Porto Alegre');
});

it('returns empty results when brazilian city file is missing', function () {
    $path = resource_path('data/br-cities/ZZ.json');

    if (File::exists($path)) {
        File::delete($path);
    }

    Cache::flush();

    $matches = app(LocationCatalogService::class)->searchBrazilianCities('BR', 'ZZ', 'Test');

    expect($matches)->toBe([]);
});

it('caches decoded catalog arrays safely', function () {
    $service = app(LocationCatalogService::class);

    $service->countryOptions();

    $cached = Cache::get('location_catalog.country_region_data');

    expect($cached)->toBeArray()
        ->and($cached)->not->toBeInstanceOf(\Illuminate\Support\Collection::class);
});

it('matches brazilian city names by normalized comparison key', function () {
    $service = app(LocationCatalogService::class);

    expect($service->matchBrazilianCityName('RS', 'porto alegre'))->toBe('Porto Alegre');
});
