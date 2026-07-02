<?php

namespace App\Filament\Resources\Cities;

use App\Filament\Resources\Cities\Pages\ManageCities;
use App\Models\City;
use App\Services\CountryRegionService;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CityResource extends Resource
{
    protected static ?string $model = City::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static ?string $navigationLabel = 'Cidades';

    protected static ?string $modelLabel = 'Cidade';

    protected static ?string $pluralModelLabel = 'Cidades';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 40;

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) auth()->user()?->hasAdvancedMode();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ])
            ->where('user_id', Auth::id());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('country_code')
                    ->label('País')
                    ->options(fn (CountryRegionService $service) => $service->countryOptions())
                    ->default(fn (string $operation): ?string => $operation === 'create'
                        ? self::defaultCountryCode()
                        : null)
                    ->searchable()
                    ->native(false)
                    ->live()
                    ->afterStateHydrated(function (callable $set, ?string $state, string $operation): void {
                        if ($operation !== 'create' || filled($state)) {
                            return;
                        }

                        $set('country_code', self::defaultCountryCode());
                        $set('region_code', self::defaultRegionCode());
                    })
                    ->afterStateUpdated(fn (callable $set) => $set('region_code', null))
                    ->required(),

                Select::make('region_code')
                    ->label('Estado / Região')
                    ->options(fn (callable $get, CountryRegionService $service) =>
                        $service->regionOptions($get('country_code'))
                    )
                    ->default(fn (string $operation): ?string => $operation === 'create'
                        ? self::defaultRegionCode()
                        : null)
                    ->searchable()
                    ->native(false)
                    ->disabled(fn (callable $get) => blank($get('country_code')))
                    ->nullable(),

                TextInput::make('name')
                    ->label('Cidade')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name')
                    ->label('Nome'),

                TextEntry::make('region_code')
                    ->label('Estado / Região')
                    ->placeholder('-'),

                TextEntry::make('created_at')
                    ->label('Criado em')
                    ->dateTime()
                    ->placeholder('-'),

                TextEntry::make('updated_at')
                    ->label('Atualizado em')
                    ->dateTime()
                    ->placeholder('-'),

                TextEntry::make('deleted_at')
                    ->label('Excluído em')
                    ->dateTime()
                    ->visible(fn (City $record): bool => $record->trashed()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('region_code')
                    ->label('Estado / Região')
                    ->placeholder('-')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Atualizado em')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('deleted_at')
                    ->label('Excluído em')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalHeading('Excluir cidade')
                    ->modalDescription('Tem certeza que deseja excluir esta cidade?'),
                ForceDeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCities::route('/'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ])
            ->where('user_id', Auth::id());
    }

    private static function defaultCountryCode(): string
    {
        # TODO: Multi default country support (lang update)
        return self::latestCityForDefaults()?->country_code ?? 'BR';
    }

    private static function defaultRegionCode(): ?string
    {
        return self::latestCityForDefaults()?->region_code;
    }

    private static function latestCityForDefaults(): ?City
    {
        return City::query()
            ->where('user_id', Auth::id())
            ->latest('created_at')
            ->first();
    }
}
