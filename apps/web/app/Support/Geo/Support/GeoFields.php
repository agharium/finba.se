<?php

namespace App\Support\Geo\Support;

use App\Support\Geo\DTO\City;
use App\Support\Geo\DTO\Country;
use App\Support\Geo\DTO\Region;
use App\Support\Geo\Exceptions\GeoException;
use App\Support\Geo\Facades\Geo;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Throwable;

/**
 * Reusable Filament location fields backed by the Geo facade.
 *
 * Form state: geo_country_code, geo_region_id, geo_city_id.
 * Only geo_city_id (external integer) is persisted on domain models.
 *
 * City options come from GET /v1/regions/{id}/cities (region-scoped).
 * Global city search is not used: the Go API has no regionId filter yet.
 */
class GeoFields
{
    /**
     * @return array<int, Component|\Filament\Schemas\Components\Component>
     */
    public static function cascade(
        bool $requireCountry = false,
        bool $requireRegion = false,
        bool $requireCity = false,
        ?callable $visible = null,
    ): array {
        $fields = [
            self::country(required: $requireCountry || $requireRegion || $requireCity),
            self::region(required: $requireRegion || $requireCity),
            self::city(required: $requireCity),
        ];

        if ($visible === null) {
            return $fields;
        }

        return array_map(
            fn (Select $field): Select => $field->visible($visible),
            $fields,
        );
    }

    public static function country(
        string $name = 'geo_country_code',
        ?string $regionField = 'geo_region_id',
        ?string $cityField = 'geo_city_id',
        bool $required = false,
    ): Select {
        $select = Select::make($name)
            ->label('País')
            ->options(function (): array {
                try {
                    return Geo::countries()
                        ->mapWithKeys(fn (Country $country): array => [$country->code => $country->name])
                        ->all();
                } catch (Throwable) {
                    return [];
                }
            })
            ->searchable()
            ->native(false)
            ->live()
            ->helperText(fn (): ?string => self::catalogUnavailableHelper())
            ->afterStateUpdated(function (Set $set) use ($regionField, $cityField): void {
                if ($regionField !== null) {
                    $set($regionField, null);
                }

                if ($cityField !== null) {
                    $set($cityField, null);
                }
            });

        if ($required) {
            $select->required();
        }

        return $select;
    }

    public static function region(
        string $name = 'geo_region_id',
        string $countryField = 'geo_country_code',
        ?string $cityField = 'geo_city_id',
        bool $required = false,
    ): Select {
        $select = Select::make($name)
            ->label('Estado / Região')
            ->options(function (Get $get) use ($countryField): array {
                $countryCode = $get($countryField);

                if (blank($countryCode)) {
                    return [];
                }

                try {
                    return Geo::regions((string) $countryCode)
                        ->mapWithKeys(fn (Region $region): array => [
                            $region->id => filled($region->code)
                                ? "{$region->name} ({$region->code})"
                                : $region->name,
                        ])
                        ->all();
                } catch (Throwable) {
                    return [];
                }
            })
            ->searchable()
            ->native(false)
            ->live()
            ->disabled(fn (Get $get): bool => blank($get($countryField)))
            ->afterStateUpdated(function (Set $set) use ($cityField): void {
                if ($cityField !== null) {
                    $set($cityField, null);
                }
            });

        if ($required) {
            $select->required();
        }

        return $select;
    }

    public static function city(
        string $name = 'geo_city_id',
        string $regionField = 'geo_region_id',
        bool $required = false,
    ): Select {
        $select = Select::make($name)
            ->label('Cidade')
            ->options(function (Get $get) use ($regionField): array {
                return self::regionCityOptions($get($regionField));
            })
            ->getSearchResultsUsing(function (?string $search, Get $get) use ($regionField): array {
                $options = self::regionCityOptions($get($regionField));

                if (blank($search) || mb_strlen(trim($search)) < 2) {
                    return $options;
                }

                $needle = mb_strtolower(trim($search));

                return collect($options)
                    ->filter(fn (string $label): bool => str_contains(mb_strtolower($label), $needle))
                    ->all();
            })
            ->searchable()
            ->searchDebounce(300)
            ->native(false)
            ->disabled(fn (Get $get): bool => blank($get($regionField)))
            ->getOptionLabelUsing(function (mixed $value): ?string {
                if (blank($value)) {
                    return null;
                }

                return app(GeoPresenter::class)->fullLabel((int) $value);
            });

        if ($required) {
            $select->required();
        }

        return $select;
    }

    /**
     * @return array<int, string>
     */
    private static function regionCityOptions(mixed $regionId): array
    {
        if (blank($regionId)) {
            return [];
        }

        try {
            return Geo::cities($regionId)
                ->mapWithKeys(fn (City $city): array => [$city->id => $city->name])
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    private static function catalogUnavailableHelper(): ?string
    {
        try {
            Geo::countries();

            return null;
        } catch (GeoException|Throwable) {
            return 'Catálogo geográfico temporariamente indisponível. Tente novamente em instantes.';
        }
    }
}
