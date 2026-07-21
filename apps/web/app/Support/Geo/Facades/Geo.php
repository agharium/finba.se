<?php

namespace App\Support\Geo\Facades;

use App\Support\Geo\Contracts\GeoContract;
use App\Support\Geo\DTO\City;
use App\Support\Geo\DTO\CityDetail;
use App\Support\Geo\DTO\Country;
use App\Support\Geo\DTO\Region;
use App\Support\Geo\GeoManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;

/**
 * @method static Collection<int, Country> countries()
 * @method static Country country(string $code)
 * @method static Collection<int, Country> searchCountries(string $query, ?int $limit = null)
 * @method static Collection<int, Region> regions(string $countryCode)
 * @method static Region region(int|string $id)
 * @method static Collection<int, Region> searchRegions(string $query, ?int $limit = null)
 * @method static Collection<int, City> cities(int|string $regionId)
 * @method static CityDetail city(int|string $id)
 * @method static Collection<int, City> searchCities(string $query, ?int $limit = null)
 *
 * @see GeoManager
 */
class Geo extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return GeoContract::class;
    }
}
