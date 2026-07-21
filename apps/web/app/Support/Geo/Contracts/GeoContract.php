<?php

namespace App\Support\Geo\Contracts;

use App\Support\Geo\DTO\City;
use App\Support\Geo\DTO\CityDetail;
use App\Support\Geo\DTO\Country;
use App\Support\Geo\DTO\Region;
use Illuminate\Support\Collection;

interface GeoContract
{
    /**
     * @return Collection<int, Country>
     */
    public function countries(): Collection;

    public function country(string $code): Country;

    /**
     * @return Collection<int, Country>
     */
    public function searchCountries(string $query, ?int $limit = null): Collection;

    /**
     * @return Collection<int, Region>
     */
    public function regions(string $countryCode): Collection;

    public function region(int|string $id): Region;

    /**
     * @return Collection<int, Region>
     */
    public function searchRegions(string $query, ?int $limit = null): Collection;

    /**
     * @return Collection<int, City>
     */
    public function cities(int|string $regionId): Collection;

    public function city(int|string $id): CityDetail;

    /**
     * Global city name search. The Go API does not currently support region scoping.
     *
     * @return Collection<int, City>
     */
    public function searchCities(string $query, ?int $limit = null): Collection;
}
