<?php

namespace App\Filament\Resources\Categories;

use App\Filament\Resources\Categories\Pages\ManageCategories;
use App\Models\Category;
use App\Models\Person;
use App\Enums\Purpose;
use App\Enums\TransactionType;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\ToggleButtons;
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
use Illuminate\Validation\Rule;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $navigationLabel = 'Categorias';

    protected static ?string $modelLabel = 'Categoria';

    protected static ?string $pluralModelLabel = 'Categorias';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 20;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('user_id', Auth::id());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                ToggleButtons::make('types')
                    ->label('Tipo')
                    ->options(TransactionType::options())
                    ->icons(TransactionType::icons())
                    ->colors(TransactionType::colors())
                    ->inline()
                    ->grouped()
                    ->required()
                    ->live()
                    ->multiple()
                    ->extraAttributes([
                        'class' => 'finba-full-toggle-buttons',
                    ])
                    ->afterStateUpdated(function (?array $state, callable $get, callable $set): void {
                        $types = $state ?? [];

                        if (! in_array(TransactionType::EXPENSE->value, $types, true)) {
                            $set('purpose', null);
                        }

                        if (blank($types)) {
                            $set('parent_id', null);
                            $set('people', []);

                            return;
                        }

                        $parentId = $get('parent_id');

                        if ($parentId) {
                            $parentStillValid = Category::query()
                                ->where('user_id', Auth::id())
                                ->whereKey($parentId)
                                ->where(function (Builder $query) use ($types) {
                                    foreach ($types as $type) {
                                        $query->orWhereJsonContains('types', $type);
                                    }
                                })
                                ->exists();

                            if (! $parentStillValid) {
                                $set('parent_id', null);
                            }
                        }

                        $peopleIds = $get('people') ?? [];

                        if (filled($peopleIds)) {
                            $validPeopleIds = Person::query()
                                ->where('user_id', Auth::id())
                                ->whereIn('id', $peopleIds)
                                ->where(function (Builder $query) use ($types) {
                                    foreach ($types as $type) {
                                        $query->orWhereJsonContains('types', $type);
                                    }
                                })
                                ->pluck('id')
                                ->all();

                            $set('people', $validPeopleIds);
                        }
                    }),

                TextInput::make('name')
                    ->label('Nome')
                    ->required()
                    ->maxLength(255)
                    ->rule(fn (callable $get) => Rule::unique('categories', 'name')
                    ->where('user_id', Auth::id())
                    ->where(fn ($query) => $get('parent_id')
                        ? $query->where('parent_id', $get('parent_id'))
                        : $query->whereNull('parent_id'))),

                Select::make('parent_id')
                    ->label('Categoria pai')
                    ->relationship(
                        name: 'parent',
                        titleAttribute: 'name',
                        modifyQueryUsing: function (Builder $query, callable $get, ?Category $record): Builder {
                            $query->where('user_id', Auth::id());

                            if (filled($get('types'))) {
                                $query->where(function (Builder $query) use ($get) {
                                    foreach ($get('types') as $type) {
                                        $query->orWhereJsonContains('types', $type);
                                    }
                                });
                            }

                            if (! $record) {
                                return $query;
                            }

                            return $query->whereNotIn('id', [
                                $record->id,
                                ...$record->descendantsIds(),
                            ]);
                        },
                    )
                    ->live()
                    ->afterStateUpdated(fn (callable $set) => $set('people', []))
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->disabled(fn (?Category $record): bool => ! Category::query()
                        ->where('user_id', Auth::id())
                        ->when(
                            $record,
                            fn (Builder $query) => $query->whereNotIn('id', [
                                $record->id,
                                ...$record->descendantsIds(),
                            ]),
                        )
                        ->exists())
                    ->helperText(fn (?Category $record): ?string => ! Category::query()
                        ->where('user_id', Auth::id())
                        ->when(
                            $record,
                            fn (Builder $query) => $query->whereNotIn('id', [
                                $record->id,
                                ...$record->descendantsIds(),
                            ]),
                        )
                        ->exists()
                            ? 'Nenhuma categoria disponível para vincular como pai.'
                            : null),

                Select::make('people')
                    ->label('Pessoas vinculadas')
                    ->helperText('Quando esta categoria for usada em uma transação, estas pessoas estarão relacionadas a ela.')
                    ->relationship(
                        name: 'people',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query, callable $get) => $query
                            ->where('people.user_id', Auth::id())
                            ->when(
                                filled($get('types')),
                                fn (Builder $query) => $query->where(function (Builder $query) use ($get) {
                                    foreach ($get('types') as $type) {
                                        $query->orWhereJsonContains('people.types', $type);
                                    }
                                }),
                            )
                            ->orderBy('people.name'),
                    )
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->disabled(fn (callable $get): bool => blank($get('types')) || filled($get('parent_id')))
                    ->visible(fn (): bool => (bool) Auth::user()?->is_advanced)
                    ->helperText(fn (callable $get): string => filled($get('parent_id'))
                        ? 'Subcategorias não podem ser vinculadas diretamente a pessoas. Vincule a categoria pai.'
                        : 'Quando esta categoria for usada em uma transação, estas pessoas estarão relacionadas a ela.'),

                Select::make('purpose')
                    ->native(false)
                    ->label('Finalidade especial')
                    ->options(Purpose::class)
                    ->nullable()
                    ->live()
                    ->required(false)
                    ->helperText('Opcional. Marque apenas categorias que representem contribuições como dízimo ou oferta. Isso impactará cálculos automáticos.')
                    ->visible(fn (): bool => (bool) Auth::user()?->is_tither)
                    ->afterStateUpdated(function (?Purpose $state, callable $get, callable $set): void {
                        if (! $state) {
                            return;
                        }

                        $types = $get('types') ?? [];

                        if (! in_array(TransactionType::EXPENSE->value, $types, true)) {
                            $types[] = TransactionType::EXPENSE->value;
                        }

                        $set('types', array_values(array_unique($types)));
                    })
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
    
                TextEntry::make('parent.name')
                    ->label('Categoria pai')
                    ->placeholder('-'),
    
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
    
                TextColumn::make('parent.name')
                    ->label('Categoria pai')
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
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalHeading('Excluir categoria')
                    ->modalDescription('Tem certeza que deseja excluir esta categoria?'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->requiresConfirmation(),
                ]),
            ]);
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
            'index' => ManageCategories::route('/'),
        ];
    }
}