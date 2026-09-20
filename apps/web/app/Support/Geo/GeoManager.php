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
     * Bump when cached payload shape changes (arrays vs DTO objects, etc.).
     */
    private const CACHE_VERSION = 'v2';

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
        $payload = $this->rememberStablePayload(
            $this->cacheKey('countries'),
            'countries',
            fn (): array => $this->requireList($this->client->get('/v1/countries'), '/v1/countries'),
        );

        return $this->mapList($payload, Country::fromArray(...));
    }

    public function country(string $code): Country
    {
        $code = strtoupper($code);
        $endpoint = "/v1/countries/{$code}";

        $payload = $this->rememberStablePayload(
            $this->cacheKey("country:{$code}"),
            'country',
            fn (): array => $this->requireObject($this->client->get($endpoint), $endpoint),
        );

        return Country::fromArray($payload);
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
        $endpoint = "/v1/countries/{$countryCode}/regions";

        $payload = $this->rememberStablePayload(
            $this->cacheKey("regions:{$countryCode}"),
            'regions',
            fn (): array => $this->requireList($this->client->get($endpoint), $endpoint),
        );

        return $this->mapList($payload, Region::fromArray(...));
    }

    public function region(int|string $id): Region
    {
        $endpoint = "/v1/regions/{$id}";

        $payload = $this->rememberStablePayload(
            $this->cacheKey("region:{$id}"),
            'region',
            fn (): array => $this->requireObject($this->client->get($endpoint), $endpoint),
        );

        return Region::fromArray($payload);
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
        $endpoint = "/v1/cities/{$id}";

        $payload = $this->rememberStablePayload(
            $this->cacheKey("city:{$id}"),
            'city',
            fn (): array => $this->requireObject($this->client->get($endpoint), $endpoint),
        );

        return CityDetail::fromArray($payload);
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

    private function cacheKey(string $suffix): string
    {
        return 'geo:'.self::CACHE_VERSION.':'.$suffix;
    }

    /**
     * Persist only JSON-safe arrays. Hydrate DTOs after read so file/redis cache
     * never returns __PHP_Incomplete_Class for Geo DTO Collections.
     *
     * @param  callable(): array<string, mixed>|list<array<string, mixed>>  $callback
     * @return array<string, mixed>|list<array<string, mixed>>
     */
    private function rememberStablePayload(string $key, string $ttlKey, callable $callback): array
    {
        if (array_key_exists($key, $this->memo)) {
            /** @var array<string, mixed>|list<array<string, mixed>> */
            return $this->memo[$key];
        }

        if (! $this->cacheEnabled) {
            return $this->memo[$key] = $callback();
        }

        $fresh = max(1, (int) ($this->cacheTtl[$ttlKey] ?? 86400));
        $stale = $fresh * 2;

        $value = $this->cache()->flexible($key, [$fresh, $stale], $callback);

        if (! is_array($value)) {
            $this->cache()->forget($key);
            $value = $callback();
        }

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
