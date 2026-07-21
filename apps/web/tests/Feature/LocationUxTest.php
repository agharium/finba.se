<?php

use App\Filament\Forms\UserPreferenceFormFields;
use App\Filament\Pages\Profile;
use App\Models\Transaction;
use App\Models\User;
use App\Services\LocationDefaultsService;
use App\Services\UserPreferencesService;
use App\Support\Geo\Contracts\GeoContract;
use App\Support\Geo\GeoClient;
use App\Support\Geo\Support\GeoCityResolver;
use App\Support\Geo\Support\GeoFields;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    prepareGeoTestEnvironment();
    fakeGeoContractApi();
});

function locationUxUser(array $overrides = []): User
{
    return User::query()->create(array_merge([
        'name' => 'Location UX User',
        'email' => fake()->unique()->safeEmail(),
        'password' => 'password',
        'email_verified_at' => now(),
        'locale' => 'pt_BR',
        'settings' => ['advanced' => true, 'locale' => 'pt_BR'],
    ], $overrides));
}

it('initializes internal country as BR for pt_BR when absent', function () {
    $user = locationUxUser([
        'geo_city_id' => null,
        'settings' => ['advanced' => true, 'locale' => 'pt_BR'],
    ]);

    Livewire::actingAs($user)
        ->test(Profile::class)
        ->assertSet('data.geo_country_code', 'BR');
});

it('uses geo prefixed fields in profile location preferences', function () {
    $names = collect(UserPreferenceFormFields::locationFields())
        ->map(fn ($component) => $component->getName())
        ->filter()
        ->all();

    expect($names)->toContain('geo_country_code', 'geo_region_id', 'geo_city_id');
});

it('exposes GeoFields cascade with geo prefixed names', function () {
    expect(GeoFields::country()->getName())->toBe('geo_country_code')
        ->and(GeoFields::region()->getName())->toBe('geo_region_id')
        ->and(GeoFields::city()->getName())->toBe('geo_city_id');
});

it('persists external geo city id from profile save', function () {
    $user = locationUxUser(['geo_city_id' => null, 'timezone' => null]);

    Livewire::actingAs($user)
        ->test(Profile::class)
        ->set('data.locale', 'pt_BR')
        ->set('data.geo_country_code', 'BR')
        ->set('data.geo_region_id', 2021)
        ->set('data.geo_city_id', 1001)
        ->call('save')
        ->assertRedirect(Profile::getUrl());

    $fresh = $user->fresh();

    expect($fresh->geo_city_id)->toBe(1001)
        ->and($fresh->timezone)->toBe('America/Sao_Paulo');
});

it('persists geo city id through UserPreferencesService', function () {
    $user = locationUxUser(['geo_city_id' => null]);

    app(UserPreferencesService::class)->persistPreferences($user, [
        'locale' => 'pt_BR',
        'geo_country_code' => 'BR',
        'geo_region_id' => 2021,
        'geo_city_id' => 1001,
        'advanced' => true,
        'tither' => false,
        'accounts_receivable' => false,
    ], requireCity: true);

    expect($user->fresh()->geo_city_id)->toBe(1001);
});

it('resets city when profile region changes', function () {
    $user = locationUxUser(['geo_city_id' => 1001]);

    Livewire::actingAs($user)
        ->test(Profile::class)
        ->set('data.geo_city_id', 1001)
        ->set('data.geo_region_id', 2022)
        ->assertSet('data.geo_city_id', null);
});

it('does not overwrite an existing saved country when locale changes', function () {
    $user = locationUxUser([
        'geo_city_id' => 1001,
        'settings' => ['advanced' => true, 'locale' => 'pt_BR'],
    ]);

    Livewire::actingAs($user)
        ->test(Profile::class)
        ->set('data.locale', 'en')
        ->set('data.geo_country_code', 'BR')
        ->set('data.geo_region_id', 2021)
        ->set('data.geo_city_id', 1001)
        ->call('save')
        ->assertRedirect(Profile::getUrl());

    expect(app(LocationDefaultsService::class)->internalCountryCode($user->fresh()))->toBe('BR');
});

it('leaves country unset for english locale without a saved country', function () {
    expect(app(LocationDefaultsService::class)->countryFromLocale('en'))->toBeNull();
});

it('rejects geo city selections inconsistent with country or region', function () {
    $resolver = app(GeoCityResolver::class);

    expect(fn () => $resolver->resolveForPersistence([
        'geo_country_code' => 'US',
        'geo_region_id' => 2021,
        'geo_city_id' => 1001,
    ]))->toThrow(ValidationException::class);

    expect(fn () => $resolver->resolveForPersistence([
        'geo_country_code' => 'BR',
        'geo_region_id' => 2022,
        'geo_city_id' => 1001,
    ]))->toThrow(ValidationException::class);
});

it('preserves saved geo city id when geo api fails during form hydration', function () {
    $user = locationUxUser(['geo_city_id' => 1001]);

    fakeGeoContractApi();
    app(UserPreferencesService::class)->defaultFormState($user);

    Http::fake([
        'geo.test/v1/cities/1001' => Http::response(['error' => 'down'], 503),
    ]);

    app()->forgetInstance(GeoClient::class);
    app()->forgetInstance(GeoContract::class);

    $state = app(UserPreferencesService::class)->defaultFormState($user);

    expect($state['geo_city_id'])->toBe(1001)
        ->and($user->fresh()->geo_city_id)->toBe(1001);
});

it('reports missing defaults through location defaults service', function () {
    $user = locationUxUser(['geo_city_id' => null]);

    expect(app(LocationDefaultsService::class)->hasConfiguredLocation($user))->toBeFalse();
});

it('keeps existing transaction geo city id on edit', function () {
    $user = locationUxUser(['geo_city_id' => 1002]);

    $transaction = Transaction::query()->create([
        'user_id' => $user->id,
        'type' => 'EXPENSE',
        'amount' => 25,
        'status' => 'PAID',
        'date' => now(),
        'geo_city_id' => 1001,
    ]);

    $transaction->update(['description' => 'Updated']);

    expect($transaction->fresh()->geo_city_id)->toBe(1001);
});

it('uses default geo city id for transaction create when configured', function () {
    $user = locationUxUser(['geo_city_id' => 1001]);

    $this->actingAs($user);

    expect(app(LocationDefaultsService::class)->cityIdForCreate($user))->toBe(1001);
});
