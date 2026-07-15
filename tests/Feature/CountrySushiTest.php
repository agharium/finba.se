<?php

use App\Exceptions\CountryCatalogException;
use App\Filament\Pages\Dashboard;
use App\Models\Country;
use App\Models\User;
use App\Support\MoneyFormatter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Country::clearSushiSqliteCaches();
    Country::flushCatalogCache();
});

it('boots countries from json without a postgresql countries table', function () {
    expect(Schema::connection(config('database.default'))->hasTable('countries'))->toBeFalse();

    $cachePath = storage_path('framework/cache/sushi-app-models-country.sqlite');
    file_put_contents($cachePath, '');

    Country::clearSushiSqliteCaches();
    Country::flushCatalogCache();

    expect(Country::query()->count())->toBeGreaterThan(200)
        ->and(Country::query()->where('code', 'BR')->value('currency'))->toBe('BRL')
        ->and(Country::query()->where('code', 'US')->value('name'))->toBe('United States')
        ->and(MoneyFormatter::currencyForCountry('PT'))->toBe('EUR');

    expect(file_exists($cachePath))->toBeFalse();
});

it('throws a useful exception when the country json is missing', function () {
    $path = resource_path('data/country-region-data.json');
    $backup = $path.'.bak-test';

    rename($path, $backup);

    try {
        Country::flushCatalogCache();
        Country::loadCatalogRows();
        $this->fail('Expected CountryCatalogException was not thrown.');
    } catch (CountryCatalogException $exception) {
        expect($exception->getMessage())->toContain('missing');
    } finally {
        rename($backup, $path);
        Country::flushCatalogCache();
    }
});

it('renders the dashboard without a real countries table', function () {
    expect(Schema::connection(config('database.default'))->hasTable('countries'))->toBeFalse();

    $user = User::query()->create([
        'name' => 'Country Sushi User',
        'email' => fake()->unique()->safeEmail(),
        'password' => 'password',
        'email_verified_at' => now(),
        'default_country_code' => 'US',
        'settings' => ['locale' => 'en'],
        'onboarding_completed_at' => now(),
    ]);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertSuccessful();

    expect(MoneyFormatter::currencyCode($user))->toBe('USD')
        ->and(Schema::connection(config('database.default'))->hasTable('countries'))->toBeFalse();
});

it('passes the country catalog artisan check', function () {
    $this->artisan('finba:country-catalog-check')
        ->assertSuccessful()
        ->expectsOutputToContain('Country catalog check passed');
});
