<?php

namespace App\Services;

class CountryRegionService
{
    public function __construct(
        private LocationCatalogService $locationCatalog,
    ) {}

    public function countryOptions(): array
    {
        return $this->locationCatalog->countryOptions();
    }

    public function regionOptions(?string $countryCode): array
    {
        return $this->locationCatalog->regionOptions($countryCode);
    }
}