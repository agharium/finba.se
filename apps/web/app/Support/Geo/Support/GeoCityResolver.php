<?php

namespace App\Support\Geo\Support;

use App\Support\Geo\DTO\CityDetail;
use App\Support\Geo\Exceptions\GeoException;
use App\Support\Geo\Exceptions\GeoNotFoundException;
use App\Support\Geo\Facades\Geo;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Write-boundary validation for external Geo city IDs.
 */
class GeoCityResolver
{
    /**
     * Hydrate cascading form state from a persisted external Geo city ID.
     *
     * @return array{geo_country_code: ?string, geo_region_id: ?int, geo_city_id: ?int}
     */
    public function formStateFromCityId(?int $geoCityId, ?string $fallbackCountryCode = null): array
    {
        if ($geoCityId === null) {
            return [
                'geo_country_code' => $fallbackCountryCode,
                'geo_region_id' => null,
                'geo_city_id' => null,
            ];
        }

        try {
            $detail = Geo::city($geoCityId);

            return [
                'geo_country_code' => $detail->country->code,
                'geo_region_id' => $detail->region?->id,
                'geo_city_id' => $detail->id,
            ];
        } catch (Throwable) {
            // Preserve the saved ID even when labels cannot be resolved.
            return [
                'geo_country_code' => $fallbackCountryCode,
                'geo_region_id' => null,
                'geo_city_id' => $geoCityId,
            ];
        }
    }

    /**
     * Validate and normalize a Geo city selection for persistence.
     *
     * @param  array<string, mixed>  $state
     */
    public function resolveForPersistence(array $state, bool $required = false): ?int
    {
        $geoCityId = $this->normalizeId($state['geo_city_id'] ?? null);
        $geoRegionId = $this->normalizeId($state['geo_region_id'] ?? null);
        $geoCountryCode = isset($state['geo_country_code']) && is_string($state['geo_country_code'])
            ? strtoupper(trim($state['geo_country_code']))
            : null;

        if ($geoCityId === null) {
            if ($required) {
                throw ValidationException::withMessages([
                    'geo_city_id' => 'Selecione a cidade.',
                ]);
            }

            return null;
        }

        if ($geoRegionId === null && $required) {
            throw ValidationException::withMessages([
                'geo_region_id' => 'Selecione o estado ou região.',
            ]);
        }

        try {
            $detail = Geo::city($geoCityId);
        } catch (GeoNotFoundException) {
            throw ValidationException::withMessages([
                'geo_city_id' => 'A cidade selecionada não está mais disponível no catálogo geográfico.',
            ]);
        } catch (GeoException|Throwable) {
            throw ValidationException::withMessages([
                'geo_city_id' => 'Não foi possível validar a cidade. Tente novamente em instantes.',
            ]);
        }

        $this->assertConsistent($detail, $geoCountryCode, $geoRegionId);

        return $detail->id;
    }

    public function timezoneForCity(int $geoCityId): ?string
    {
        try {
            $timezone = Geo::city($geoCityId)->timezone;
        } catch (Throwable) {
            return null;
        }

        if (! is_string($timezone) || trim($timezone) === '') {
            return null;
        }

        return $timezone;
    }

    private function assertConsistent(CityDetail $detail, ?string $countryCode, ?int $regionId): void
    {
        if (filled($countryCode) && strtoupper($detail->country->code) !== $countryCode) {
            throw ValidationException::withMessages([
                'geo_city_id' => 'A cidade selecionada não pertence ao país escolhido.',
            ]);
        }

        if ($regionId !== null && ($detail->region?->id ?? null) !== $regionId) {
            throw ValidationException::withMessages([
                'geo_city_id' => 'A cidade selecionada não pertence à região escolhida.',
            ]);
        }
    }

    private function normalizeId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (is_string($value) && ctype_digit($value)) {
            $int = (int) $value;

            return $int > 0 ? $int : null;
        }

        throw ValidationException::withMessages([
            'geo_city_id' => 'Identificador de cidade inválido.',
        ]);
    }
}
