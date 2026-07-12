<?php

namespace App\Filament\Forms;

use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;

class UserPreferenceFormFields
{
    /**
     * @return array<int, \Filament\Forms\Components\Component|\Filament\Schemas\Components\Component>
     */
    public static function locationFields(bool $requireRegion = false, bool $requireCity = false): array
    {
        return [
            LocationFormFields::profileLocaleSelect(),
            LocationFormFields::profileHiddenCountryField(),
            LocationFormFields::profileRegionSelect(required: $requireRegion),
            LocationFormFields::profileDefaultCitySelect(required: $requireCity),
        ];
    }

    /**
     * @return array<int, \Filament\Forms\Components\Component|\Filament\Schemas\Components\Component>
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
