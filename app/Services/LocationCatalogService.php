<?php

namespace App\Services;

use App\Support\LocationNameNormalizer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LocationCatalogService
{
    private const COUNTRY_REGION_CACHE_KEY = 'location_catalog.country_region_data';

    private const BR_CITIES_CACHE_PREFIX = 'location_catalog.br_cities.';

    private const DEFAULT_CITY_LIMIT = 50;

    private const FREQUENT_CITY_LIMIT = 10;

    public function countryOptions(): array
    {
        return collect($this->countryRegionData())
            ->mapWithKeys(fn (array $country): array => [
                $country['countryShortCode'] => $country['countryName'],
            ])
            ->sort()
            ->all();
    }

    public function regionOptions(?string $countryCode): array
    {
        if (blank($countryCode)) {
            return [];
        }

        $country = collect($this->countryRegionData())
            ->firstWhere('countryShortCode', $countryCode);

        if ($country === null) {
            return [];
        }

        return collect($country['regions'] ?? [])
            ->mapWithKeys(fn (array $region): array => [
                $region['shortCode'] ?? $region['name'] => $region['name'],
            ])
            ->sort()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function searchBrazilianCities(?string $countryCode, ?string $regionCode, ?string $search = null, int $limit = self::DEFAULT_CITY_LIMIT): array
    {
        if ($countryCode !== 'BR' || blank($regionCode)) {
            return [];
        }

        $cities = $this->brazilianCitiesForRegion($regionCode);

        if ($cities === []) {
            return [];
        }

        if (filled($search)) {
            $needle = LocationNameNormalizer::normalizeForComparison($search);

            $cities = array_values(array_filter(
                $cities,
                fn (string $city): bool => str_contains(
                    LocationNameNormalizer::normalizeForComparison($city) ?? '',
                    $needle ?? '',
                ),
            ));
        }

        sort($cities, SORT_NATURAL | SORT_FLAG_CASE);

        return array_slice($cities, 0, $limit);
    }

    public function frequentCityLimit(): int
    {
        return self::FREQUENT_CITY_LIMIT;
    }

    public function matchBrazilianCityName(string $regionCode, string $comparisonName): ?string
    {
        foreach ($this->brazilianCitiesForRegion($regionCode) as $cityName) {
            if (LocationNameNormalizer::normalizeForComparison($cityName) === $comparisonName) {
                return $cityName;
            }
        }

        return null;
    }

    /**
     * @return list<array{countryShortCode: string, countryName: string, regions: array<int, array<string, mixed>>}>
     */
    private function countryRegionData(): array
    {
        $cached = Cache::get(self::COUNTRY_REGION_CACHE_KEY);

        if (is_array($cached)) {
            return $cached;
        }

        $path = resource_path('data/country-region-data.json');

        if (! File::exists($path)) {
            Log::error('Location catalog country-region file missing.', ['path' => $path]);

            return [];
        }

        try {
            $data = json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable $exception) {
            Log::error('Location catalog country-region file invalid.', [
                'path' => $path,
                'message' => $exception->getMessage(),
            ]);

            return [];
        }

        if (! is_array($data)) {
            Log::error('Location catalog country-region file did not decode to an array.', ['path' => $path]);

            return [];
        }

        Cache::forever(self::COUNTRY_REGION_CACHE_KEY, $data);

        return $data;
    }

    /**
     * @return list<string>
     */
    private function brazilianCitiesForRegion(string $regionCode): array
    {
        $regionCode = Str::upper($regionCode);
        $cacheKey = self::BR_CITIES_CACHE_PREFIX.$regionCode;
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        $path = resource_path("data/br-cities/{$regionCode}.json");

        if (! File::exists($path)) {
            Log::warning('Brazilian city catalog file missing.', [
                'path' => $path,
                'region_code' => $regionCode,
            ]);

            return [];
        }

        try {
            $data = json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable $exception) {
            Log::error('Brazilian city catalog file invalid.', [
                'path' => $path,
                'message' => $exception->getMessage(),
            ]);

            return [];
        }

        if (! is_array($data)) {
            Log::error('Brazilian city catalog file did not decode to an array.', ['path' => $path]);

            return [];
        }

        $cities = array_values(array_filter(
            $data,
            fn (mixed $city): bool => is_string($city) && filled($city),
        ));

        Cache::forever($cacheKey, $cities);

        return $cities;
    }
}
