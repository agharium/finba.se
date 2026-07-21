<?php

namespace App\Support\Geo;

use App\Support\Geo\Contracts\GeoContract;
use App\Support\Geo\DTO\City;
use App\Support\Geo\DTO\CityDetail;
use App\Support\Geo\DTO\Country;
use App\Support\Geo\DTO\Region;
use App\Support\Geo\Exceptions\GeoResponseException;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class GeoManager implements GeoContract
{
    /**
     * Request-scoped memoization for hot lookups (bounded by this instance lifetime).
     *
     * @var array<string, mixed>
     */
    private array $memo = [];

    /**
     * @param  array{countries: int, country: int, regions: int, region: int, city: int}  $cacheTtl
     */
    public function __construct(
        private readonly GeoClient $client,
        private readonly bool $cacheEnabled,
        private readonly array $cacheTtl,
        private readonly ?string $cacheStore = null,
    ) {}

    public function countries(): Collection
    {
        return $this->rememberStable(
            'geo:v1:countries',
            'countries',
            function (): Collection {
                return $this->mapList(
                    $this->requireList($this->client->get('/v1/countries'), '/v1/countries'),
                    Country::fromArray(...),
                );
            },
        );
    }

    public function country(string $code): Country
    {
        $code = strtoupper($code);

        return $this->rememberStable(
            "geo:v1:country:{$code}",
            'country',
            function () use ($code): Country {
                $endpoint = "/v1/countries/{$code}";

                return Country::fromArray($this->requireObject($this->client->get($endpoint), $endpoint));
            },
        );
    }

    public function searchCountries(string $query, ?int $limit = null): Collection
    {
        $endpoint = '/v1/countries/search';

        return $this->mapList(
            $this->requireList($this->client->get($endpoint, $this->searchQuery($query, $limit)), $endpoint),
            Country::fromArray(...),
        );
    }

    public function regions(string $countryCode): Collection
    {
        $countryCode = strtoupper($countryCode);

        return $this->rememberStable(
            "geo:v1:regions:{$countryCode}",
            'regions',
            function () use ($countryCode): Collection {
                $endpoint = "/v1/countries/{$countryCode}/regions";

                return $this->mapList(
                    $this->requireList($this->client->get($endpoint), $endpoint),
                    Region::fromArray(...),
                );
            },
        );
    }

    public function region(int|string $id): Region
    {
        return $this->rememberStable(
            "geo:v1:region:{$id}",
            'region',
            function () use ($id): Region {
                $endpoint = "/v1/regions/{$id}";

                return Region::fromArray($this->requireObject($this->client->get($endpoint), $endpoint));
            },
        );
    }

    public function searchRegions(string $query, ?int $limit = null): Collection
    {
        $endpoint = '/v1/regions/search';

        return $this->mapList(
            $this->requireList($this->client->get($endpoint, $this->searchQuery($query, $limit)), $endpoint),
            Region::fromArray(...),
        );
    }

    public function cities(int|string $regionId): Collection
    {
        $memoKey = "cities:{$regionId}";

        if (array_key_exists($memoKey, $this->memo)) {
            /** @var Collection<int, City> */
            return $this->memo[$memoKey];
        }

        $endpoint = "/v1/regions/{$regionId}/cities";

        $cities = $this->mapList(
            $this->requireList($this->client->get($endpoint), $endpoint),
            City::fromArray(...),
        );

        $this->memo[$memoKey] = $cities;

        return $cities;
    }

    public function city(int|string $id): CityDetail
    {
        return $this->rememberStable(
            "geo:v1:city:{$id}",
            'city',
            function () use ($id): CityDetail {
                $endpoint = "/v1/cities/{$id}";

                return CityDetail::fromArray($this->requireObject($this->client->get($endpoint), $endpoint));
            },
        );
    }

    public function searchCities(string $query, ?int $limit = null): Collection
    {
        $endpoint = '/v1/cities/search';

        return $this->mapList(
            $this->requireList($this->client->get($endpoint, $this->searchQuery($query, $limit)), $endpoint),
            City::fromArray(...),
        );
    }

    /**
     * @return array{q: string, limit?: int}
     */
    private function searchQuery(string $query, ?int $limit): array
    {
        $params = ['q' => $query];

        if ($limit !== null) {
            $params['limit'] = $limit;
        }

        return $params;
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function rememberStable(string $key, string $ttlKey, callable $callback): mixed
    {
        if (array_key_exists($key, $this->memo)) {
            return $this->memo[$key];
        }

        if (! $this->cacheEnabled) {
            return $this->memo[$key] = $callback();
        }

        $fresh = max(1, (int) ($this->cacheTtl[$ttlKey] ?? 86400));
        $stale = $fresh * 2;

        $value = $this->cache()->flexible($key, [$fresh, $stale], $callback);

        return $this->memo[$key] = $value;
    }

    private function cache(): CacheRepository
    {
        return Cache::store($this->cacheStore);
    }

    /**
     * @param  array<string, mixed>|list<mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function requireList(array $payload, string $endpoint): array
    {
        if (! array_is_list($payload)) {
            throw new GeoResponseException(
                'Geo service expected a JSON array response.',
                endpoint: $endpoint,
            );
        }

        foreach ($payload as $item) {
            if (! is_array($item)) {
                throw new GeoResponseException(
                    'Geo service list contained a non-object item.',
                    endpoint: $endpoint,
                );
            }
        }

        /** @var list<array<string, mixed>> $payload */
        return $payload;
    }

    /**
     * @param  array<string, mixed>|list<mixed>  $payload
     * @return array<string, mixed>
     */
    private function requireObject(array $payload, string $endpoint): array
    {
        if ($payload === [] || array_is_list($payload)) {
            throw new GeoResponseException(
                'Geo service expected a JSON object response.',
                endpoint: $endpoint,
            );
        }

        /** @var array<string, mixed> $payload */
        return $payload;
    }

    /**
     * @template T of object
     *
     * @param  list<array<string, mixed>>  $items
     * @param  callable(array<string, mixed>): T  $mapper
     * @return Collection<int, T>
     */
    private function mapList(array $items, callable $mapper): Collection
    {
        return collect($items)->values()->map(fn (array $item) => $mapper($item));
    }
}
