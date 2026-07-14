<?php

use App\Models\User;
use App\Support\MoneyFormatter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Number;

uses(RefreshDatabase::class);

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
        'default_country_code' => 'US',
        'settings' => ['locale' => 'en'],
    ]);

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
        'default_country_code' => 'BR',
        'settings' => ['locale' => 'pt-BR'],
    ]);

    expect(MoneyFormatter::format(1234.5, $user))->toBe(Number::currency(1234.5, in: 'BRL', locale: 'pt_BR'))
        ->and(MoneyFormatter::symbol($user))->toBe('R$')
        ->and(MoneyFormatter::currencyCode($user))->toBe('BRL');
});
