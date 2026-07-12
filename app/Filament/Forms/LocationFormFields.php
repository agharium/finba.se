<?php

namespace App\Filament\Forms;

use App\Filament\Pages\Profile;
use App\Models\City;
use App\Models\User;
use App\Services\LocationCatalogService;
use App\Services\LocationDefaultsService;
use App\Services\UserCityService;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Facades\Auth;

class LocationFormFields
{
    public static function profileLocaleSelect(): Select
    {
        return Select::make('locale')
            ->label('Idioma')
            ->options(LocationDefaultsService::SUPPORTED_LOCALES)
            ->default(fn (): string => app(LocationDefaultsService::class)->inferLocale())
            ->native(false)
            ->live()
            ->afterStateUpdated(function (?string $state, Set $set): void {
                if (filled(auth()->user()?->default_country_code)) {
                    return;
                }

                $set('default_country_code', app(LocationDefaultsService::class)->countryFromLocale((string) $state));
                $set('default_region_code', null);
                $set('default_city_id', null);
            })
            ->required();
    }

    public static function profileHiddenCountryField(): Hidden
    {
        return Hidden::make('default_country_code');
    }

    public static function profileRegionSelect(bool $required = false): Select
    {
        return self::regionSelect(
            countryField: 'default_country_code',
            name: 'default_region_code',
            cityField: 'default_city_id',
            required: $required,
        )->disabled(fn (Get $get): bool => blank($get('default_country_code')));
    }

    public static function profileDefaultCitySelect(bool $required = false): Select
    {
        return self::buildCitySelect(
            countryResolver: fn (Get $get): ?string => $get('default_country_code'),
            regionResolver: fn (Get $get): ?string => $get('default_region_code'),
            name: 'default_city_id',
            required: $required,
            nullable: ! $required,
            useConfiguredDefaultsGate: false,
        );
    }

    public static function regionSelect(
        string $countryField = 'country_code',
        string $name = 'region_code',
        ?string $cityField = null,
        bool $live = true,
        ?callable $defaultResolver = null,
        bool $required = true,
    ): Select {
        $select = Select::make($name)
            ->label('Estado / Região')
            ->options(fn (Get $get, LocationCatalogService $service): array => $service->regionOptions($get($countryField)))
            ->default($defaultResolver)
            ->searchable()
            ->native(false)
            ->live($live)
            ->disabled(fn (Get $get): bool => blank($get($countryField)))
            ->afterStateUpdated(fn (Set $set) => self::resetCityFields($set, $countryField, $cityField));

        if ($required) {
            $select->required();
        }

        return $select;
    }

    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    public static function compactLocationPicker(
        string $cityField = 'city_id',
        bool $multiple = false,
        ?callable $visible = null,
        ?string $cityHelperText = null,
    ): array {
        $visible ??= fn (): bool => true;

        return [
            ViewField::make('location_configuration_prompt')
                ->dehydrated(false)
                ->visible(fn (Get $get): bool => $visible()
                    && ! app(LocationDefaultsService::class)->hasConfiguredLocation(Auth::user())
                    && ! (bool) $get('search_other_region'))
                ->view('filament.forms.location-configuration-prompt')
                ->viewData([
                    'profileUrl' => Profile::getUrl(),
                ]),

            self::buildCitySelect(
                countryResolver: fn (Get $get): ?string => self::activeSearchCountry($get),
                regionResolver: fn (Get $get): ?string => self::activeSearchRegion($get),
                name: $cityField,
                multiple: $multiple,
                required: false,
                nullable: ! $multiple,
                visible: fn (Get $get): bool => $visible() && app(LocationDefaultsService::class)->canSearchCities($get),
                helperText: $cityHelperText,
                useConfiguredDefaultsGate: true,
            ),

            Toggle::make('search_other_region')
                ->label('Buscar em outro estado')
                ->dehydrated(false)
                ->live()
                ->visible(fn (Get $get): bool => $visible()
                    && app(LocationDefaultsService::class)->hasConfiguredLocation(Auth::user()))
                ->afterStateUpdated(function (bool $state, Set $set) use ($cityField, $multiple): void {
                    if (! $state) {
                        $set('temporary_region_code', null);
                    }

                    if ($multiple) {
                        $set($cityField, []);
                    } else {
                        $set($cityField, null);
                    }
                }),

            self::regionSelect(
                countryField: '_internal_country_code',
                name: 'temporary_region_code',
                cityField: $cityField,
                live: true,
                required: false,
            )
                ->dehydrated(false)
                ->options(fn (LocationCatalogService $service): array => $service->regionOptions(
                    app(LocationDefaultsService::class)->internalCountryCode(Auth::user()),
                ))
                ->disabled(fn (): bool => blank(app(LocationDefaultsService::class)->internalCountryCode(Auth::user())))
                ->visible(fn (Get $get): bool => $visible() && (bool) $get('search_other_region'))
                ->afterStateUpdated(function (Set $set) use ($cityField, $multiple): void {
                    if ($multiple) {
                        $set($cityField, []);
                    } else {
                        $set($cityField, null);
                    }
                }),

            ViewField::make('location_override_hint')
                ->dehydrated(false)
                ->visible(fn (Get $get): bool => $visible() && (bool) $get('search_other_region'))
                ->view('filament.forms.location-override-hint')
                ->viewData([
                    'profileUrl' => Profile::getUrl(),
                ]),
        ];
    }

    public static function manualCityInput(
        string $countryField = 'country_code',
        string $regionField = 'region_code',
        string $name = 'manual_city_name',
    ): TextInput {
        return TextInput::make($name)
            ->label('Cidade')
            ->helperText('Digite o nome da cidade quando não houver catálogo disponível para este país.')
            ->visible(fn (Get $get): bool => filled($get($countryField))
                && filled($get($regionField))
                && $get($countryField) !== 'BR')
            ->required(fn (Get $get): bool => filled($get($countryField))
                && filled($get($regionField))
                && $get($countryField) !== 'BR');
    }

    public static function userDefaultCountryCode(): ?string
    {
        return app(LocationDefaultsService::class)->internalCountryCode(Auth::user());
    }

    public static function userDefaultRegionCode(): ?string
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user?->default_region_code;
    }

    public static function userDefaultCityId(): ?string
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user?->default_city_id;
    }

    public static function hasConfiguredDefaults(): bool
    {
        return app(LocationDefaultsService::class)->hasConfiguredLocation(Auth::user());
    }

    public static function activeSearchCountry(Get $get): ?string
    {
        return app(LocationDefaultsService::class)->searchContextFromFormState($get)['country_code'];
    }

    public static function activeSearchRegion(Get $get): ?string
    {
        return app(LocationDefaultsService::class)->searchContextFromFormState($get)['region_code'];
    }

    /**
     * @return array<int, string>
     */
    public static function ephemeralFormFieldNames(): array
    {
        return [
            'search_other_region',
            'temporary_region_code',
            'location_configuration_prompt',
            'location_override_hint',
        ];
    }

    public static function stripEphemeralFields(array $data): array
    {
        foreach (self::ephemeralFormFieldNames() as $field) {
            unset($data[$field]);
        }

        return $data;
    }

    private static function buildCitySelect(
        callable $countryResolver,
        callable $regionResolver,
        string $name,
        bool $multiple = false,
        bool $required = false,
        bool $nullable = true,
        ?callable $visible = null,
        ?string $helperText = null,
        bool $useConfiguredDefaultsGate = true,
    ): Select {
        $select = Select::make($name)
            ->label($multiple ? 'Cidades vinculadas' : 'Cidade')
            ->searchable()
            ->native(false)
            ->disabled(function (Get $get) use ($countryResolver, $regionResolver, $useConfiguredDefaultsGate): bool {
                if ($useConfiguredDefaultsGate) {
                    return ! app(LocationDefaultsService::class)->canSearchCities($get);
                }

                return blank($countryResolver($get)) || blank($regionResolver($get));
            })
            ->getSearchResultsUsing(fn (?string $search, Get $get): array => app(UserCityService::class)->citySelectOptions(
                Auth::user(),
                $countryResolver($get),
                $regionResolver($get),
                $search,
            ))
            ->getOptionLabelUsing(function (?string $value, Get $get) use ($regionResolver): ?string {
                return app(UserCityService::class)->optionLabel(
                    $value,
                    $regionResolver($get),
                );
            })
            ->options(fn (Get $get): array => app(UserCityService::class)->citySelectOptions(
                Auth::user(),
                $countryResolver($get),
                $regionResolver($get),
            ))
            ->dehydrateStateUsing(function (mixed $state, Get $get) use ($countryResolver, $regionResolver, $multiple): mixed {
                $user = Auth::user();
                $country = $countryResolver($get);
                $region = $regionResolver($get);
                $service = app(UserCityService::class);

                if ($multiple) {
                    return collect($state ?? [])
                        ->map(fn (string $value): ?string => $service->resolveCatalogSelection($user, $country, $region, $value))
                        ->filter()
                        ->unique()
                        ->values()
                        ->all();
                }

                return $service->resolveCatalogSelection($user, $country, $region, is_string($state) ? $state : null);
            })
            ->live();

        if ($multiple) {
            $select->multiple();
        }

        if ($nullable) {
            $select->nullable();
        }

        if ($required) {
            $select->required();
        }

        if ($visible !== null) {
            $select->visible($visible);
        }

        if ($helperText !== null) {
            $select->helperText($helperText);
        }

        return $select;
    }

    private static function resetCityFields(Set $set, string $countryField, ?string $cityField = null): void
    {
        if ($cityField !== null) {
            $set($cityField, null);

            return;
        }

        if ($countryField === 'default_country_code') {
            $set('default_city_id', null);

            return;
        }

        $set('default_city_id', null);
        $set('city_id', null);
        $set('cities', []);
    }
}
