<?php

namespace App\Services;

use App\Models\City;

class CityUsageService
{
    public function record(?City $city): void
    {
        if (! $city instanceof City) {
            return;
        }

        $city->increment('usage_count');
        $city->forceFill(['last_used_at' => now()])->save();
    }

    public function recordIfChanged(?City $city, mixed $previousCityId): void
    {
        if (! $city instanceof City) {
            return;
        }

        if ((string) $previousCityId === (string) $city->getKey()) {
            return;
        }

        $this->record($city);
    }

    /**
     * @param  list<string>  $newCityIds
     * @param  list<string>  $previousCityIds
     */
    public function recordNewlyAttached(array $newCityIds, array $previousCityIds): void
    {
        $addedIds = array_diff($newCityIds, $previousCityIds);

        if ($addedIds === []) {
            return;
        }

        City::query()
            ->whereKey($addedIds)
            ->get()
            ->each(fn (City $city): mixed => $this->record($city));
    }
}
