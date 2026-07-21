<?php

namespace App\Support\Geo\DTO;

use App\Support\Geo\Exceptions\GeoResponseException;

/**
 * City detail from GET /v1/cities/{id} with nested region/country.
 */
readonly class CityDetail
{
    public function __construct(
        public int $id,
        public string $name,
        public CountrySummary $country,
        public ?RegionSummary $region = null,
        public ?string $timezone = null,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        if (! isset($payload['id'], $payload['name'], $payload['country']) || ! is_array($payload['country'])) {
            throw new GeoResponseException(
                'City detail payload is missing required fields (id, name, country).',
            );
        }

        $region = null;

        if (isset($payload['region'])) {
            if (! is_array($payload['region'])) {
                throw new GeoResponseException('City detail region must be an object when present.');
            }

            $region = RegionSummary::fromArray($payload['region']);
        }

        return new self(
            id: (int) $payload['id'],
            name: (string) $payload['name'],
            country: CountrySummary::fromArray($payload['country']),
            region: $region,
            timezone: isset($payload['timezone']) && $payload['timezone'] !== ''
                ? (string) $payload['timezone']
                : null,
        );
    }
}
