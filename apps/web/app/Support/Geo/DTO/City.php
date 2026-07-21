<?php

namespace App\Support\Geo\DTO;

use App\Support\Geo\Exceptions\GeoResponseException;

/**
 * City list / search item from GET /v1/regions/{id}/cities and GET /v1/cities/search.
 */
readonly class City
{
    public function __construct(
        public int $id,
        public int $countryId,
        public string $name,
        public ?int $regionId = null,
        public ?string $timezone = null,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        if (! isset($payload['id'], $payload['countryId'], $payload['name'])) {
            throw new GeoResponseException('City payload is missing required fields (id, countryId, name).');
        }

        return new self(
            id: (int) $payload['id'],
            countryId: (int) $payload['countryId'],
            name: (string) $payload['name'],
            regionId: array_key_exists('regionId', $payload) && $payload['regionId'] !== null
                ? (int) $payload['regionId']
                : null,
            timezone: isset($payload['timezone']) && $payload['timezone'] !== ''
                ? (string) $payload['timezone']
                : null,
        );
    }
}
