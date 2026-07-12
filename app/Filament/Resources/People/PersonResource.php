<?php

namespace App\Filament\Resources\People;

use App\Enums\TransactionType;
use App\Filament\Forms\LocationFormFields;
use App\Filament\Resources\People\Pages\ManagePeople;
use App\Models\Category;
use App\Models\Person;
use App\Services\LocationDefaultsService;
use App\Services\UserCityService;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;

class PersonResource extends Resource
{
    protected static ?string $model = Person::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $navigationLabel = 'Pessoas';

    protected static ?string $modelLabel = 'Pessoa';

    protected static ?string $pluralModelLabel = 'Pessoas';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 30;

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) auth()->user()?->hasAdvancedMode();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('cities')
            ->where('user_id', Auth::id());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nome')
                    ->required()
                    ->maxLength(255),
    
                CheckboxList::make('types')
                    ->label('Tipos')
                    ->options(TransactionType::options())
                    ->columns(2)
                    ->required()
                    ->minItems(1)
                    ->live()
                    ->afterStateUpdated(fn (callable $set) => $set('categories', [])),
    
                Select::make('categories')
                    ->label('Categorias vinculadas')
                    ->helperText('Quando esta pessoa for selecionada em uma transação, estas categorias serão disponibilizadas.')
                    ->relationship(
                        name: 'categories',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query, callable $get) => $query
                            ->where('categories.user_id', Auth::id())
                            ->whereNull('categories.parent_id')
                            ->when(
                                filled($get('types')),
                                fn (Builder $query) => $query->where(function (Builder $query) use ($get) {
                                    foreach ($get('types') as $type) {
                                        $query->orWhereJsonContains('categories.types', $type);
                                    }
                                }),
                            )
                            ->orderBy('categories.name'),
                    )
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->columnSpanFull()
                    ->disabled(fn (callable $get): bool => blank($get('types')))
                    ->visible(fn (): bool => (bool) Auth::user()?->hasAdvancedMode()),

                ...LocationFormFields::compactLocationPicker(
                    cityField: 'cities',
                    multiple: true,
                    visible: fn (): bool => (bool) Auth::user()?->hasAdvancedMode(),
                    cityHelperText: 'Quando esta pessoa for selecionada em uma transação, estas cidades poderão ser escolhidas.',
                ),

            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name')
                    ->label('Nome'),

                TextEntry::make('types')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (array|string|null $state): string => self::formatTypes($state)),

                TextEntry::make('cities.name')
                    ->label('Cidades vinculadas')
                    ->badge()
                    ->placeholder('-')
                    ->visible(fn (): bool => (bool) Auth::user()?->hasAdvancedMode()),

                TextEntry::make('created_at')
                    ->label('Criado em')
                    ->dateTime()
                    ->placeholder('-'),

                TextEntry::make('updated_at')
                    ->label('Atualizado em')
                    ->dateTime()
                    ->placeholder('-'),
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
                
                TextColumn::make('types')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (array|string|null $state): string => self::formatTypes($state))
                    ->color(fn (array|string|null $state): string => self::typesColor($state)),

                TextColumn::make('cities.name')
                    ->label('Cidades')
                    ->badge()
                    ->limitList(3)
                    ->placeholder('-')
                    ->visible(fn (): bool => (bool) Auth::user()?->hasAdvancedMode()),

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
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->fillForm(fn (Person $record): array => [
                        'name' => $record->name,
                        'types' => $record->types,
                        'categories' => $record->categories()->pluck('categories.id')->all(),
                        'cities' => $record->cities()->pluck('cities.id')->all(),
                    ])
                    ->using(fn (Person $record, array $data): Person => self::savePerson($record, $data)),
                DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalHeading('Excluir pessoa')
                    ->modalDescription('Tem certeza que deseja excluir esta pessoa?'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->requiresConfirmation(),
                ]),
            ]);
    }

    public static function savePerson(Person $record, array $data): Person
    {
        $previousCityIds = $record->exists
            ? $record->cities()->pluck('cities.id')->all()
            : [];

        $cityIds = self::resolveCityIds($data);
        $categoryIds = $data['categories'] ?? [];
        $data = LocationFormFields::stripEphemeralFields($data);
        unset($data['cities'], $data['categories']);

        $record->fill($data);
        $record->save();

        $record->categories()->sync($categoryIds);
        $record->cities()->sync($cityIds);

        app(\App\Services\CityUsageService::class)->recordNewlyAttached($cityIds, $previousCityIds);

        return $record;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    public static function resolveCityIds(array $data): array
    {
        $user = Auth::user();
        $defaults = app(LocationDefaultsService::class);

        if ((bool) ($data['search_other_region'] ?? false)) {
            $countryCode = $user?->default_country_code;
            $regionCode = $data['temporary_region_code'] ?? null;
        } else {
            $countryCode = $user?->default_country_code;
            $regionCode = $user?->default_region_code;
        }

        return collect($data['cities'] ?? [])
            ->map(fn (string $value): ?string => app(UserCityService::class)->resolveCatalogSelection(
                $user,
                $countryCode,
                $regionCode,
                $value,
            ))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private static function formatTypes(array|string|null $state): string
    {
        return TransactionType::listLabel($state);
    }

    private static function typesColor(array|string|null $state): string
    {
        return TransactionType::listColor($state);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePeople::route('/'),
        ];
    }
}