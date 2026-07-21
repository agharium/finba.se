<?php

namespace App\Support\Geo\Support;

use App\Support\Geo\DTO\CityDetail;
use App\Support\Geo\Exceptions\GeoNotFoundException;
use App\Support\Geo\Facades\Geo;
use Throwable;

/**
 * Safe label resolution for external Geo city IDs.
 *
 * Does not mutate stored IDs. Uses GeoManager cache + request memoization.
 */
class GeoPresenter
{
    public const UNAVAILABLE = 'Location temporarily unavailable';

    public const MISSING = 'Location no longer available';

    public function detail(?int $geoCityId): ?CityDetail
    {
        if ($geoCityId === null) {
            return null;
        }

        try {
            return Geo::city($geoCityId);
        } catch (Throwable) {
            return null;
        }
    }

    public function cityLabel(?int $geoCityId): ?string
    {
        if ($geoCityId === null) {
            return null;
        }

        try {
            return Geo::city($geoCityId)->name;
        } catch (GeoNotFoundException) {
            return self::MISSING;
        } catch (Throwable) {
            return self::UNAVAILABLE;
        }
    }

    public function cityRegionLabel(?int $geoCityId): ?string
    {
        if ($geoCityId === null) {
            return null;
        }

        try {
            $city = Geo::city($geoCityId);
            $region = $city->region?->code ?: $city->region?->name;

            return filled($region) ? "{$city->name} ({$region})" : $city->name;
        } catch (GeoNotFoundException) {
            return self::MISSING;
        } catch (Throwable) {
            return self::UNAVAILABLE;
        }
    }

    public function fullLabel(?int $geoCityId): ?string
    {
        if ($geoCityId === null) {
            return null;
        }

        try {
            $city = Geo::city($geoCityId);
            $parts = array_filter([
                $city->name,
                $city->region?->code ?: $city->region?->name,
                $city->country->code,
            ]);

            return implode(' · ', $parts);
        } catch (GeoNotFoundException) {
            return self::MISSING;
        } catch (Throwable) {
            return self::UNAVAILABLE;
        }
    }

    /**
     * Labels for distinct Geo city IDs (paginated tables / filters).
     *
     * @param  iterable<int|string|null>  $geoCityIds
     * @return array<int, string>
     */
    public function labelsForIds(iterable $geoCityIds): array
    {
        $labels = [];

        foreach ($geoCityIds as $id) {
            if ($id === null || $id === '') {
                continue;
            }

            $intId = (int) $id;
            $labels[$intId] = $this->fullLabel($intId) ?? (string) $intId;
        }

        asort($labels, SORT_NATURAL | SORT_FLAG_CASE);

        return $labels;
    }
}
