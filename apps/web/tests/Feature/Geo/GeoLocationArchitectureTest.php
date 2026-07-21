<?php

use App\Models\Person;
use App\Models\Transaction;
use App\Models\User;
use App\Services\UserPreferencesService;
use App\Support\Geo\Contracts\GeoContract;
use App\Support\Geo\Facades\Geo;
use App\Support\Geo\GeoClient;
use App\Support\Geo\Support\GeoPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    prepareGeoTestEnvironment();
    Cache::flush();
});

it('stores geo_city_id as unsigned integers without a cities table', function () {
    expect(Schema::hasTable('cities'))->toBeFalse()
        ->and(Schema::hasColumn('users', 'geo_city_id'))->toBeTrue()
        ->and(Schema::hasColumn('transactions', 'geo_city_id'))->toBeTrue()
        ->and(Schema::hasColumn('people', 'geo_city_id'))->toBeTrue();
});

it('casts geo_city_id to int on user transaction and person models', function () {
    $user = User::factory()->create(['geo_city_id' => '1001']);
    $person = Person::query()->create([
        'user_id' => $user->id,
        'name' => 'Geo Person',
        'types' => ['CONTACT'],
        'geo_city_id' => '1001',
    ]);
    $transaction = Transaction::query()->create([
        'user_id' => $user->id,
        'type' => 'EXPENSE',
        'amount' => 10,
        'status' => 'PAID',
        'date' => now(),
        'geo_city_id' => '1001',
    ]);

    expect($user->fresh()->geo_city_id)->toBeInt()->toBe(1001)
        ->and($person->fresh()->geo_city_id)->toBeInt()->toBe(1001)
        ->and($transaction->fresh()->geo_city_id)->toBeInt()->toBe(1001);
});

it('does not expose a local City relationship on User', function () {
    expect(method_exists(User::class, 'city'))->toBeFalse();
});

it('persists external geo city id through preferences', function () {
    fakeGeoContractApi();

    $user = User::factory()->create(['geo_city_id' => null]);

    app(UserPreferencesService::class)->persistPreferences($user, [
        'locale' => 'pt_BR',
        'geo_country_code' => 'BR',
        'geo_region_id' => 2021,
        'geo_city_id' => 1001,
        'advanced' => false,
        'tither' => false,
        'accounts_receivable' => false,
    ], requireCity: true);

    expect($user->fresh()->geo_city_id)->toBe(1001);
});

it('serves cached city detail when http fails after cache warm', function () {
    fakeGeoContractApi();

    expect(Geo::city(1001)->name)->toBe('Tramandaí');

    Http::fake([
        'geo.test/v1/cities/1001' => Http::response(['error' => 'down'], 503),
    ]);

    app()->forgetInstance(GeoClient::class);
    app()->forgetInstance(GeoContract::class);

    expect(Geo::city(1001)->name)->toBe('Tramandaí');
});

it('returns presenter labels for unavailable and missing cities', function () {
    fakeGeoContractApi();

    $presenter = app(GeoPresenter::class);

    expect($presenter->fullLabel(1001))->toContain('Tramandaí');

    Http::fake([
        'geo.test/v1/cities/4040' => Http::response(geoFixture('error_city_not_found'), 404),
    ]);

    app()->forgetInstance(GeoClient::class);
    app()->forgetInstance(GeoContract::class);

    expect($presenter->cityLabel(4040))->toBe(GeoPresenter::MISSING);

    Http::fake([
        'geo.test/v1/cities/5050' => Http::response(['error' => 'down'], 503),
    ]);

    app()->forgetInstance(GeoClient::class);
    app()->forgetInstance(GeoContract::class);

    expect($presenter->cityLabel(5050))->toBe(GeoPresenter::UNAVAILABLE);
});

it('removed legacy local catalog classes from the architecture', function () {
    expect(file_exists(app_path('Models/City.php')))->toBeFalse()
        ->and(file_exists(app_path('Services/UserCityService.php')))->toBeFalse();
});
