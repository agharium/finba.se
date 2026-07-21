<?php

namespace App\Support;

use App\Enums\Locale;
use App\Models\User;
use App\Services\LocationDefaultsService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Number;
use NumberFormatter;

class MoneyFormatter
{
    private const DEFAULT_CURRENCY = 'BRL';

    public static function format(float|int|string|null $amount, ?User $user = null): string
    {
        $user ??= Auth::user();

        return Number::currency(
            (float) ($amount ?? 0),
            in: self::currencyCode($user),
            locale: self::numberLocale($user),
        );
    }

    public static function currencyCode(?User $user = null): string
    {
        $user ??= Auth::user();
        $locationDefaults = app(LocationDefaultsService::class);

        $countryCode = $locationDefaults->internalCountryCode($user);

        if (blank($countryCode) && $user) {
            $countryCode = $locationDefaults->countryFromLocale($user->preferredLocale());
        }

        return self::currencyForCountry($countryCode);
    }

    public static function currencyForCountry(?string $countryCode): string
    {
        if (blank($countryCode)) {
            return self::DEFAULT_CURRENCY;
        }

        return CurrencyCatalog::currencyForCountry($countryCode);
    }

    public static function symbol(?User $user = null): string
    {
        $formatter = new NumberFormatter(self::numberLocale($user), NumberFormatter::CURRENCY);

        return $formatter->getSymbol(NumberFormatter::CURRENCY_SYMBOL);
    }

    /**
     * BCP 47 locale for browser JavaScript consumers (e.g. toLocaleString).
     */
    public static function browserInputLocale(?User $user = null): string
    {
        return self::resolveLocale($user)->browserLocale();
    }

    /**
     * ICU locale for PHP NumberFormatter / Illuminate\Support\Number.
     */
    public static function numberLocale(?User $user = null): string
    {
        return self::resolveLocale($user)->icuLocale();
    }

    private static function resolveLocale(?User $user): Locale
    {
        $user ??= Auth::user();

        if (! $user) {
            return Locale::default();
        }

        return Locale::fromNullable($user->preferredLocale());
    }
}
