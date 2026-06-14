<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

class CountryRegionService
{
    private const CACHE_KEY = 'country_region_data';

    public function countries(): Collection
    {
        return $this->data()
            ->map(fn (array $country) => [
                'code' => $country['countryShortCode'],
                'name' => $country['countryName'],
            ])
            ->sortBy('name')
            ->values();
    }

    public function countryOptions(): array
    {
        return $this->countries()
            ->pluck('name', 'code')
            ->all();
    }

    public function regionOptions(?string $countryCode): array
    {
        if (! $countryCode) {
            return [];
        }

        $country = $this->data()
            ->firstWhere('countryShortCode', $countryCode);

        return collect($country['regions'] ?? [])
            ->mapWithKeys(fn (array $region) => [
                $region['shortCode'] ?? $region['name'] => $region['name'],
            ])
            ->sort()
            ->all();
    }

    private function data(): Collection
    {
        $cached = Cache::get(self::CACHE_KEY);

        if ($cached instanceof Collection) {
            return $cached;
        }

        if (is_array($cached)) {
            return collect($cached);
        }

        if ($cached !== null) {
            Cache::forget(self::CACHE_KEY);
        }

        $path = resource_path('data/country-region-data.json');

        $data = json_decode(
            File::get($path),
            true,
            flags: JSON_THROW_ON_ERROR
        );

        Cache::forever(self::CACHE_KEY, $data);

        return collect($data);
    }
}