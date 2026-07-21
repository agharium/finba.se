<?php

use App\Models\User;
use App\Support\Geo\Facades\Geo;
use App\Support\MoneyFormatter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Number;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    prepareGeoTestEnvironment();
});

function moneyFormatterUserWithGeoCity(User $user, int $geoCityId = 1001): User
{
    fakeGeoContractApi();
    $user->update(['geo_city_id' => $geoCityId]);

    return $user->fresh();
}

function moneyFormatterUserWithEsCity(User $user): User
{
    Http::fake([
        'geo.test/v1/cities/9002' => Http::response([
            'id' => 9002,
            'name' => 'Madrid',
            'timezone' => 'Europe/Madrid',
            'region' => [
                'id' => 5002,
                'code' => 'MD',
                'name' => 'Madrid',
            ],
            'country' => [
                'id' => 2,
                'code' => 'ES',
                'name' => 'Spain',
            ],
        ]),
    ]);

    Geo::city(9002);
    $user->update(['geo_city_id' => 9002]);

    return $user->fresh();
}

function moneyFormatterUserWithUsCity(User $user): User
{
    Http::fake([
        'geo.test/v1/cities/9001' => Http::response([
            'id' => 9001,
            'name' => 'Los Angeles',
            'timezone' => 'America/Los_Angeles',
            'region' => [
                'id' => 5001,
                'code' => 'CA',
                'name' => 'California',
            ],
            'country' => [
                'id' => 1,
                'code' => 'US',
                'name' => 'United States',
            ],
        ]),
    ]);

    Geo::city(9001);
    $user->update(['geo_city_id' => 9001]);

    return $user->fresh();
}

test('currency code follows the country for supported countries', function (string $country, string $currency) {
    expect(MoneyFormatter::currencyForCountry($country))->toBe($currency);
})->with([
    ['BR', 'BRL'],
    ['US', 'USD'],
    ['PT', 'EUR'],
    ['GB', 'GBP'],
    ['CA', 'CAD'],
    ['AR', 'ARS'],
]);

test('currency falls back to BRL when country is missing or unknown', function (?string $country) {
    expect(MoneyFormatter::currencyForCountry($country))->toBe('BRL');
})->with([
    [null],
    [''],
    ['ZZ'],
]);

test('format uses the authenticated user country currency', function () {
    $user = User::query()->create([
        'name' => 'Money Formatter User',
        'email' => fake()->unique()->safeEmail(),
        'password' => 'password',
        'locale' => 'en',
        'settings' => ['locale' => 'en'],
    ]);
    $user = moneyFormatterUserWithUsCity($user);

    $this->actingAs($user);

    expect(MoneyFormatter::format(1234.5))->toBe(Number::currency(1234.5, in: 'USD', locale: 'en_US'))
        ->and(MoneyFormatter::symbol())->toBe('$')
        ->and(MoneyFormatter::currencyCode())->toBe('USD');
});

test('format uses brazilian currency for brazilian users', function () {
    $user = User::query()->create([
        'name' => 'Money Formatter BR User',
        'email' => fake()->unique()->safeEmail(),
        'password' => 'password',
        'locale' => 'pt_BR',
        'settings' => ['locale' => 'pt_BR'],
    ]);
    $user = moneyFormatterUserWithGeoCity($user, 1001);

    expect(MoneyFormatter::format(1234.5, $user))->toBe(Number::currency(1234.5, in: 'BRL', locale: 'pt_BR'))
        ->and(MoneyFormatter::symbol($user))->toBe('R$')
        ->and(MoneyFormatter::currencyCode($user))->toBe('BRL');
});

test('numberLocale is ICU and browserInputLocale is BCP 47', function () {
    $english = User::factory()->create(['locale' => 'en']);
    $portuguese = User::factory()->create(['locale' => 'pt_BR']);
    $spanish = User::factory()->create(['locale' => 'es']);

    expect(MoneyFormatter::numberLocale($english))->toBe('en_US')
        ->and(MoneyFormatter::browserInputLocale($english))->toBe('en-US')
        ->and(MoneyFormatter::numberLocale($portuguese))->toBe('pt_BR')
        ->and(MoneyFormatter::browserInputLocale($portuguese))->toBe('pt-BR')
        ->and(MoneyFormatter::numberLocale($spanish))->toBe('es_ES')
        ->and(MoneyFormatter::browserInputLocale($spanish))->toBe('es-ES');
});

test('PHP NumberFormatter accepts the ICU numberLocale', function (string $locale, string $icuLocale) {
    $user = User::factory()->create(['locale' => $locale]);

    expect(MoneyFormatter::numberLocale($user))->toBe($icuLocale);

    $formatter = new NumberFormatter($icuLocale, NumberFormatter::DECIMAL);

    expect($formatter)->toBeInstanceOf(NumberFormatter::class)
        ->and($formatter->getLocale())->toStartWith(explode('_', $icuLocale)[0]);
})->with([
    ['en', 'en_US'],
    ['pt_BR', 'pt_BR'],
    ['es', 'es_ES'],
]);

test('localized decimal formatting uses ICU grouping and separators', function (string $locale, string $expected) {
    $user = User::factory()->create(['locale' => $locale]);

    expect(Number::format(1234.56, precision: 2, locale: MoneyFormatter::numberLocale($user)))
        ->toBe($expected);
})->with([
    ['en', '1,234.56'],
    ['pt_BR', '1.234,56'],
    ['es', '1.234,56'],
]);

test('localized money formatting works for each supported locale', function (string $locale, string $country, string $currency) {
    $user = User::factory()->create(['locale' => $locale]);

    $user = match ($country) {
        'US' => moneyFormatterUserWithUsCity($user),
        'ES' => moneyFormatterUserWithEsCity($user),
        default => moneyFormatterUserWithGeoCity($user, 1001),
    };

    $formatted = MoneyFormatter::format(1234.56, $user);

    expect($formatted)->toBe(Number::currency(1234.56, in: $currency, locale: MoneyFormatter::numberLocale($user)))
        ->and($formatted)->toContain(match ($locale) {
            'en' => '1,234.56',
            'pt_BR', 'es' => '1.234,56',
        });
})->with([
    ['en', 'US', 'USD'],
    ['pt_BR', 'BR', 'BRL'],
    ['es', 'ES', 'EUR'],
]);
