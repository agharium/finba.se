<?php

namespace App\Support\Geo\DTO;

use App\Support\Geo\Exceptions\GeoResponseException;

readonly class Region
{
    public function __construct(
        public int $id,
        public int $countryId,
        public string $name,
        public ?string $code = null,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        if (! isset($payload['id'], $payload['countryId'], $payload['name'])) {
            throw new GeoResponseException('Region payload is missing required fields (id, countryId, name).');
        }

        return new self(
            id: (int) $payload['id'],
            countryId: (int) $payload['countryId'],
            name: (string) $payload['name'],
            code: isset($payload['code']) && $payload['code'] !== ''
                ? (string) $payload['code']
                : null,
        );
    }
}
