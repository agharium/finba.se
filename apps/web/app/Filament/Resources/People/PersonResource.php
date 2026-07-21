<?php

namespace App\Filament\Resources\People;

use App\Enums\TransactionType;
use App\Filament\Resources\People\Pages\ManagePeople;
use App\Models\Person;
use App\Support\Geo\Support\GeoCityResolver;
use App\Support\Geo\Support\GeoFields;
use App\Support\Geo\Support\GeoPresenter;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

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

                ...GeoFields::cascade(
                    visible: fn (): bool => (bool) Auth::user()?->hasAdvancedMode(),
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

                TextEntry::make('geo_city_id')
                    ->label('Cidade')
                    ->formatStateUsing(fn (?int $state): ?string => app(GeoPresenter::class)->fullLabel($state))
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

                TextColumn::make('geo_city_id')
                    ->label('Cidade')
                    ->formatStateUsing(fn (?int $state): ?string => app(GeoPresenter::class)->cityLabel($state))
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
                        ...app(GeoCityResolver::class)->formStateFromCityId($record->geo_city_id),
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
        $categoryIds = $data['categories'] ?? [];

        if (Auth::user()?->hasAdvancedMode()) {
            $data['geo_city_id'] = app(GeoCityResolver::class)->resolveForPersistence($data, required: false);
        } else {
            $data['geo_city_id'] = null;
        }

        unset($data['categories'], $data['geo_country_code'], $data['geo_region_id']);

        $record->fill($data);
        $record->save();

        $record->categories()->sync($categoryIds);

        return $record;
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
