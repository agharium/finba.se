<?php

namespace App\Support;

use App\Models\Country;
use App\Models\User;
use App\Services\LocationDefaultsService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Number;
use NumberFormatter;

class MoneyFormatter
{
    private const DEFAULT_CURRENCY = 'BRL';

    private const DEFAULT_NUMBER_LOCALE = 'pt_BR';

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
            $countryCode = $locationDefaults->countryFromLocale($locationDefaults->getLocale($user));
        }

        return self::currencyForCountry($countryCode);
    }

    public static function currencyForCountry(?string $countryCode): string
    {
        if (blank($countryCode)) {
            return self::DEFAULT_CURRENCY;
        }

        $currency = Country::query()
            ->where('code', strtoupper($countryCode))
            ->value('currency');

        return filled($currency) ? (string) $currency : self::DEFAULT_CURRENCY;
    }

    public static function symbol(?User $user = null): string
    {
        $formatter = new NumberFormatter(self::numberLocale($user), NumberFormatter::CURRENCY);

        return $formatter->getSymbol(NumberFormatter::CURRENCY_SYMBOL);
    }

    public static function inputLocale(?User $user = null): string
    {
        return match (self::numberLocale($user)) {
            'en_US' => 'en-US',
            default => 'pt-BR',
        };
    }

    public static function numberLocale(?User $user = null): string
    {
        $user ??= Auth::user();

        if (! $user) {
            return self::DEFAULT_NUMBER_LOCALE;
        }

        return match (app(LocationDefaultsService::class)->getLocale($user)) {
            'en' => 'en_US',
            'pt-BR' => 'pt_BR',
            default => self::DEFAULT_NUMBER_LOCALE,
        };
    }
}
