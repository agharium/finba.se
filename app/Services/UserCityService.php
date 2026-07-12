<?php

namespace App\Services;

use App\Models\City;
use App\Models\User;
use App\Support\LocationNameNormalizer;
use Illuminate\Support\Collection;

class UserCityService
{
    public function __construct(
        private LocationCatalogService $locationCatalog,
    ) {}

    public function findOrCreate(User $user, ?string $countryCode, ?string $regionCode, string $name): City
    {
        $displayName = $this->resolveDisplayName($countryCode, $regionCode, $name);

        $existing = $this->findExisting($user, $countryCode, $regionCode, $displayName);

        if ($existing instanceof City) {
            if ($existing->trashed()) {
                $existing->restore();
            }

            return $existing;
        }

        return City::query()->create([
            'user_id' => $user->id,
            'country_code' => $countryCode,
            'region_code' => $regionCode,
            'name' => $displayName,
        ]);
    }

    public function findExisting(User $user, ?string $countryCode, ?string $regionCode, string $name): ?City
    {
        $displayName = LocationNameNormalizer::normalize($name);

        if ($displayName === null) {
            return null;
        }

        $comparisonName = LocationNameNormalizer::normalizeForComparison($displayName);

        return City::query()
            ->withTrashed()
            ->where('user_id', $user->id)
            ->where('country_code', $countryCode)
            ->where('region_code', $regionCode)
            ->get()
            ->first(function (City $city) use ($comparisonName): bool {
                return LocationNameNormalizer::normalizeForComparison($city->name) === $comparisonName;
            });
    }

    /**
     * @return Collection<int, City>
     */
    public function frequentCities(User $user, ?string $countryCode, ?string $regionCode, int $limit = 10): Collection
    {
        if (blank($countryCode) || blank($regionCode)) {
            return collect();
        }

        return City::query()
            ->where('user_id', $user->id)
            ->where('country_code', $countryCode)
            ->where('region_code', $regionCode)
            ->where(function ($query): void {
                $query->where('usage_count', '>', 0)
                    ->orWhereNotNull('last_used_at');
            })
            ->orderByDesc('usage_count')
            ->orderByDesc('last_used_at')
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }

    /**
     * @return array<string, string>
     */
    public function citySelectOptions(User $user, ?string $countryCode, ?string $regionCode, ?string $search = null): array
    {
        if (blank($countryCode) || blank($regionCode)) {
            return [];
        }

        $frequent = $this->frequentCities($user, $countryCode, $regionCode, $this->locationCatalog->frequentCityLimit());
        $frequentNames = $frequent
            ->map(fn (City $city): string => LocationNameNormalizer::normalizeForComparison($city->name) ?? '')
            ->filter()
            ->all();

        $options = [];

        if ($frequent->isNotEmpty() && blank($search)) {
            $options['Mais usadas'] = $frequent
                ->mapWithKeys(fn (City $city): array => [$city->id => $city->name])
                ->all();
        }

        if ($countryCode === 'BR') {
            $catalogMatches = $this->locationCatalog->searchBrazilianCities($countryCode, $regionCode, $search);

            if (blank($search) && $frequent->isNotEmpty()) {
                $catalogMatches = array_values(array_filter(
                    $catalogMatches,
                    fn (string $cityName): bool => ! in_array(
                        LocationNameNormalizer::normalizeForComparison($cityName) ?? '',
                        $frequentNames,
                        true,
                    ),
                ));
            }

            $catalogOptions = [];

            foreach ($catalogMatches as $cityName) {
                $existing = $this->findExisting($user, $countryCode, $regionCode, $cityName);

                if ($existing instanceof City) {
                    if ($frequent->contains('id', $existing->id)) {
                        continue;
                    }

                    $catalogOptions[$existing->id] = $existing->name;

                    continue;
                }

                $catalogOptions[self::catalogOptionKey($cityName)] = $cityName;
            }

            if ($catalogOptions !== []) {
                $options['Todas as cidades'] = $catalogOptions;
            }
        } elseif (filled($search)) {
            $manualMatches = City::query()
                ->where('user_id', $user->id)
                ->where('country_code', $countryCode)
                ->where('region_code', $regionCode)
                ->whereRaw('lower(name) like ?', ['%'.strtolower($search).'%'])
                ->orderBy('name')
                ->limit(50)
                ->get()
                ->mapWithKeys(fn (City $city): array => [$city->id => $city->name])
                ->all();

            if ($manualMatches !== []) {
                $options['Cidades salvas'] = $manualMatches;
            }
        }

        if (blank($search) && $frequent->isEmpty() && $options === [] && $countryCode !== 'BR') {
            $saved = City::query()
                ->where('user_id', $user->id)
                ->where('country_code', $countryCode)
                ->where('region_code', $regionCode)
                ->orderByDesc('usage_count')
                ->orderByDesc('last_used_at')
                ->orderBy('name')
                ->limit(10)
                ->get()
                ->mapWithKeys(fn (City $city): array => [$city->id => $city->name])
                ->all();

            if ($saved !== []) {
                $options['Cidades salvas'] = $saved;
            }
        }

        return $options;
    }

    public static function catalogOptionKey(string $cityName): string
    {
        return 'catalog:'.LocationNameNormalizer::normalizeForComparison($cityName);
    }

    public static function isCatalogOptionKey(?string $value): bool
    {
        return is_string($value) && str_starts_with($value, 'catalog:');
    }

    public static function catalogNameFromOptionKey(string $key): ?string
    {
        if (! self::isCatalogOptionKey($key)) {
            return null;
        }

        return substr($key, strlen('catalog:'));
    }

    public function optionLabel(?string $value, ?string $regionCode): ?string
    {
        if (blank($value)) {
            return null;
        }

        if (self::isCatalogOptionKey($value)) {
            $comparisonName = self::catalogNameFromOptionKey($value);

            return $this->locationCatalog->matchBrazilianCityName(
                (string) $regionCode,
                (string) $comparisonName,
            ) ?? $comparisonName;
        }

        if (! \Illuminate\Support\Str::isUuid($value)) {
            return null;
        }

        return City::query()->find($value)?->name;
    }

    public function resolveCatalogSelection(User $user, ?string $countryCode, ?string $regionCode, ?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        if (self::isCatalogOptionKey($value)) {
            $comparisonName = self::catalogNameFromOptionKey($value);
            $catalogName = $this->locationCatalog->matchBrazilianCityName(
                (string) $regionCode,
                (string) $comparisonName,
            ) ?? $comparisonName;

            return $this->findOrCreate($user, $countryCode, $regionCode, $catalogName)->id;
        }

        if (! \Illuminate\Support\Str::isUuid($value)) {
            return null;
        }

        $city = City::query()->find($value);

        if (! $city instanceof City || $city->user_id !== $user->id) {
            return null;
        }

        return $value;
    }

    private function resolveDisplayName(?string $countryCode, ?string $regionCode, string $name): string
    {
        $normalized = LocationNameNormalizer::normalize($name);

        if ($normalized === null) {
            return $name;
        }

        if ($countryCode === 'BR' && filled($regionCode)) {
            $catalogName = $this->locationCatalog->matchBrazilianCityName(
                $regionCode,
                LocationNameNormalizer::normalizeForComparison($normalized) ?? '',
            );

            if ($catalogName !== null) {
                return $catalogName;
            }
        }

        return $normalized;
    }
}
