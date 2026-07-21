<?php

namespace App\Support\Geo\DTO;

use App\Support\Geo\Exceptions\GeoResponseException;

readonly class CountrySummary
{
    public function __construct(
        public int $id,
        public string $code,
        public string $name,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        if (! isset($payload['id'], $payload['code'], $payload['name'])) {
            throw new GeoResponseException('Country summary payload is missing required fields.');
        }

        return new self(
            id: (int) $payload['id'],
            code: (string) $payload['code'],
            name: (string) $payload['name'],
        );
    }
}
