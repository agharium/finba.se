<?php

namespace App\Filament\Forms;

use App\Enums\Locale;
use App\Services\LocationDefaultsService;
use App\Support\Geo\Support\GeoFields;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

class UserPreferenceFormFields
{
    /**
     * Profile / onboarding location fields backed by the Geo service.
     *
     * Form state uses geo_* identifiers. Only geo_city_id (external integer)
     * is persisted on users.geo_city_id.
     *
     * @return array<int, Component|\Filament\Schemas\Components\Component>
     */
    public static function locationFields(bool $requireRegion = false, bool $requireCity = false): array
    {
        return [
            self::localeSelect(),
            ...GeoFields::cascade(
                requireRegion: $requireRegion,
                requireCity: $requireCity,
            ),
        ];
    }

    public static function localeSelect(): Select
    {
        return Select::make('locale')
            ->label('Idioma')
            ->options(Locale::options())
            ->default(fn (): string => app(LocationDefaultsService::class)->inferLocale())
            ->native(false)
            ->live()
            ->afterStateUpdated(function (?string $state, Set $set): void {
                if (filled(auth()->user()?->geo_city_id)) {
                    return;
                }

                $set('geo_country_code', app(LocationDefaultsService::class)->countryFromLocale((string) $state));
                $set('geo_region_id', null);
                $set('geo_city_id', null);
            })
            ->required();
    }

    /**
     * @return array<int, Component|\Filament\Schemas\Components\Component>
     */
    public static function featureToggles(string $nestedGroupClass = 'finba-profile-advanced-nested'): array
    {
        return [
            self::advancedToggle(),
            self::nestedAccountsReceivableGroup($nestedGroupClass),
            self::titherToggle(),
        ];
    }

    public static function advancedToggle(): Toggle
    {
        return Toggle::make('advanced')
            ->label('Modo avançado')
            ->helperText('Desbloqueia empréstimos, pessoas, subcategorias e outros recursos extras.')
            ->live()
            ->afterStateUpdated(function (bool $state, callable $set): void {
                if (! $state) {
                    $set('accounts_receivable', false);
                }
            });
    }

    public static function nestedAccountsReceivableGroup(string $groupClass = 'finba-profile-advanced-nested'): Group
    {
        return Group::make([
            Section::make('Recursos avançados adicionais')
                ->schema([
                    Toggle::make('accounts_receivable')
                        ->label('Recebo pagamentos depois')
                        ->helperText('Ative para controlar vendas a prazo, fiado e contas a receber.'),
                ])
                ->compact(),
        ])
            ->visible(fn (Get $get): bool => (bool) $get('advanced'))
            ->extraAttributes([
                'class' => $groupClass,
            ]);
    }

    public static function titherToggle(): Toggle
    {
        return Toggle::make('tither')
            ->label('Calcular dízimos, ofertas e primícias')
            ->helperText('Habilita ferramentas para cálculo automático com base nas suas movimentações.');
    }
}
