<?php

namespace App\Models;

use App\Exceptions\CountryCatalogException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Sushi\Sushi;

class Country extends Model
{
    use Sushi;

    /**
     * @var list<array{code: string, name: string, currency: string|null}>|null
     */
    protected static ?array $catalogRows = null;

    /**
     * Prefer in-memory SQLite for this small static catalog.
     * File-based Sushi caches can race under concurrent requests and leave an
     * empty database without a countries table.
     */
    protected function sushiShouldCache(): bool
    {
        return false;
    }

    /**
     * Keep any accidental cache writes under a writable runtime directory.
     */
    protected function sushiCacheDirectory(): string
    {
        $directory = storage_path('framework/cache/sushi');

        if (! is_dir($directory)) {
            File::ensureDirectoryExists($directory);
        }

        return realpath($directory) ?: $directory;
    }

    /**
     * Invalidate cache when the JSON source or currency map changes.
     */
    protected function sushiCacheReferencePath(): string
    {
        return resource_path('data/country-region-data.json');
    }

    /**
     * @return list<array{code: string, name: string, currency: string|null}>
     */
    public function getRows(): array
    {
        return static::$catalogRows ??= static::loadCatalogRows();
    }

    /**
     * @return list<array{code: string, name: string, currency: string|null}>
     */
    public static function loadCatalogRows(): array
    {
        $path = resource_path('data/country-region-data.json');

        if (! File::exists($path)) {
            throw CountryCatalogException::missingSource($path);
        }

        try {
            $data = json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable $exception) {
            throw CountryCatalogException::invalidSource($path, $exception->getMessage());
        }

        if (! is_array($data) || $data === []) {
            throw CountryCatalogException::invalidSource($path, 'expected a non-empty array of countries.');
        }

        $currencies = static::currencyMap();
        $rows = [];

        foreach ($data as $index => $country) {
            if (! is_array($country)) {
                throw CountryCatalogException::invalidSource($path, "entry at index {$index} is not an object.");
            }

            $code = isset($country['countryShortCode']) ? strtoupper((string) $country['countryShortCode']) : '';
            $name = isset($country['countryName']) ? trim((string) $country['countryName']) : '';

            if ($code === '' || $name === '') {
                throw CountryCatalogException::invalidSource(
                    $path,
                    "entry at index {$index} is missing countryShortCode or countryName.",
                );
            }

            $rows[] = [
                'code' => $code,
                'name' => $name,
                'currency' => $currencies[$code] ?? null,
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, string>
     */
    protected static function currencyMap(): array
    {
        $path = resource_path('data/country-currencies.php');

        if (! File::exists($path)) {
            return [];
        }

        $map = require $path;

        return is_array($map) ? $map : [];
    }

    public static function flushCatalogCache(): void
    {
        static::$catalogRows = null;
        static::$sushiConnection = null;
    }

    public static function clearSushiSqliteCaches(): void
    {
        $directory = storage_path('framework/cache');

        if (! is_dir($directory)) {
            return;
        }

        foreach (glob($directory.DIRECTORY_SEPARATOR.'sushi-*.sqlite') ?: [] as $file) {
            @unlink($file);
        }

        $sushiDirectory = $directory.DIRECTORY_SEPARATOR.'sushi';

        if (is_dir($sushiDirectory)) {
            foreach (glob($sushiDirectory.DIRECTORY_SEPARATOR.'sushi-*.sqlite') ?: [] as $file) {
                @unlink($file);
            }
        }
    }
}
