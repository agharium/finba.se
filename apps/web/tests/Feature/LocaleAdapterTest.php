<?php

use App\Enums\Locale;
use App\Services\ChangelogService;

it('exposes external locale adapters for each canonical locale', function (Locale $locale, array $expected) {
    expect($locale->value)->toBe($expected['value'])
        ->and($locale->carbonLocale())->toBe($expected['carbon'])
        ->and($locale->icuLocale())->toBe($expected['icu'])
        ->and($locale->htmlLocale())->toBe($expected['html'])
        ->and($locale->browserLocale())->toBe($expected['browser'])
        ->and($locale->acceptLanguageHeader())->toBe($expected['acceptLanguage'])
        ->and($locale->fakerLocale())->toBe($expected['faker']);
})->with([
    'en' => [Locale::English, [
        'value' => 'en',
        'carbon' => 'en',
        'icu' => 'en_US',
        'html' => 'en',
        'browser' => 'en-US',
        'acceptLanguage' => 'en-US,en;q=0.9',
        'faker' => 'en_US',
    ]],
    'pt_BR' => [Locale::PortugueseBrazil, [
        'value' => 'pt_BR',
        'carbon' => 'pt_BR',
        'icu' => 'pt_BR',
        'html' => 'pt-BR',
        'browser' => 'pt-BR',
        'acceptLanguage' => 'pt-BR,pt;q=0.9',
        'faker' => 'pt_BR',
    ]],
    'es' => [Locale::Spanish, [
        'value' => 'es',
        'carbon' => 'es',
        'icu' => 'es_ES',
        'html' => 'es',
        'browser' => 'es-ES',
        'acceptLanguage' => 'es-ES,es;q=0.9',
        'faker' => 'es_ES',
    ]],
]);

it('normalizes aliases and falls back to English for unsupported values', function (?string $raw, string $expected) {
    config(['app.locale' => 'en']);

    expect(Locale::fromNullable($raw)->value)->toBe($expected);
})->with([
    [null, 'en'],
    ['', 'en'],
    ['invalid', 'en'],
    ['pt', 'pt_BR'],
    ['pt-BR', 'pt_BR'],
    ['pt_PT', 'pt_BR'],
    ['en-US', 'en'],
    ['en_GB', 'en'],
    ['es-MX', 'es'],
    ['es_AR', 'es'],
]);

it('formats changelog dates with locale-aware patterns', function (string $locale, string $expected) {
    expect(app(ChangelogService::class)->formatEntryDate('2026-07-10', $locale))
        ->toBe($expected);
})->with([
    ['pt_BR', '10 de julho de 2026'],
    ['en', 'July 10, 2026'],
    ['es', '10 de julio de 2026'],
]);
