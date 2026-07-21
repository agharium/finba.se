<?php

namespace App\Support;

/**
 * ISO 3166-1 alpha-2 → ISO 4217 currency map for MoneyFormatter.
 *
 * This is not a geographic catalog — only currency codes keyed by country.
 */
class CurrencyCatalog
{
    private const DEFAULT = 'BRL';

    /**
     * @var array<string, string>|null
     */
    private static ?array $map = null;

    public static function currencyForCountry(?string $countryCode): string
    {
        if (blank($countryCode)) {
            return self::DEFAULT;
        }

        $code = strtoupper($countryCode);

        return self::map()[$code] ?? self::DEFAULT;
    }

    /**
     * @return array<string, string>
     */
    private static function map(): array
    {
        return self::$map ??= require resource_path('data/country-currencies.php');
    }
}
