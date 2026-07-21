<?php

namespace App\Support\Geo\DTO;

use App\Support\Geo\Exceptions\GeoResponseException;

readonly class RegionSummary
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $code = null,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        if (! isset($payload['id'], $payload['name'])) {
            throw new GeoResponseException('Region summary payload is missing required fields.');
        }

        return new self(
            id: (int) $payload['id'],
            name: (string) $payload['name'],
            code: isset($payload['code']) && $payload['code'] !== ''
                ? (string) $payload['code']
                : null,
        );
    }
}
