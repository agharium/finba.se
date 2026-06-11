<?php

namespace App\Filament\Resources\Categories;

use App\Filament\Resources\Categories\Pages\ManageCategories;
use App\Models\Category;
use App\Enums\Purpose;
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
use Illuminate\Validation\Rule;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Categorias';

    protected static ?string $modelLabel = 'Categoria';

    protected static ?string $pluralModelLabel = 'Categorias';

    protected static ?string $recordTitleAttribute = 'name';

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
                        modifyQueryUsing: function (Builder $query, ?Category $record): Builder {
                            $query->where('user_id', Auth::id());
                
                            if (! $record) {
                                return $query;
                            }
                
                            return $query->whereNotIn('id', [
                                $record->id,
                                ...$record->descendantsIds(),
                            ]);
                        },
                    )
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

                Select::make('purpose')
                    ->native(false)
                    ->label('Finalidade especial')
                    ->options(Purpose::class)
                    ->nullable()
                    ->live()
                    ->required(false)
                    ->helperText('Opcional. Marque apenas categorias que representem contribuições como dízimo ou oferta. Isso impactará cálculos automáticos.')
                    ->visible(fn (): bool => (bool) Auth::user()?->is_tither)
                    ->afterStateUpdated(function (?Purpose $state, callable $set) {
                        if ($state) {
                            $set('types', ['EXPENSE']);
                        }
                    }),

                CheckboxList::make('types')
                    ->label('Tipo')
                    ->options([
                        'INCOME' => 'Receita',
                        'EXPENSE' => 'Despesa',
                    ])
                    ->required()
                    ->minItems(1)
                    ->live()
                    ->disabled(fn (callable $get): bool => filled($get('purpose')))
                    ->afterStateUpdated(fn (callable $set) => $set('categories', [])),
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
        $types = is_array($state) ? $state : [$state];

        $hasIncome = in_array('INCOME', $types, true);
        $hasExpense = in_array('EXPENSE', $types, true);

        return match (true) {
            $hasIncome && $hasExpense => 'Receita + Despesa',
            $hasIncome => 'Receita',
            $hasExpense => 'Despesa',
            default => '-',
        };
    }

    private static function typesColor(array|string|null $state): string
    {
        $types = is_array($state) ? $state : [$state];

        $hasIncome = in_array('INCOME', $types, true);
        $hasExpense = in_array('EXPENSE', $types, true);

        return match (true) {
            $hasIncome && $hasExpense => 'info',
            $hasIncome => 'success',
            $hasExpense => 'danger',
            default => 'gray',
        };
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCategories::route('/'),
        ];
    }
}