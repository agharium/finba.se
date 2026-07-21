<?php

namespace App\Services;

readonly class TitheDeliverySelection
{
    public function __construct(
        public bool $deliverTithe = false,
        public bool $deliverOffering = false,
        public bool $deliverFirstfruits = false,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            deliverTithe: (bool) ($data['deliver_tithe'] ?? false),
            deliverOffering: (bool) ($data['deliver_offering'] ?? false),
            deliverFirstfruits: (bool) ($data['deliver_firstfruits'] ?? false),
        );
    }

    public function hasSelection(): bool
    {
        return $this->deliverTithe
            || $this->deliverOffering
            || $this->deliverFirstfruits;
    }
}
