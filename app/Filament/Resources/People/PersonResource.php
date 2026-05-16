<?php

namespace App\Filament\Resources\People;

use App\Filament\Resources\People\Pages\ManagePeople;
use App\Models\Person;
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

class PersonResource extends Resource
{
    protected static ?string $model = Person::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $navigationLabel = 'Pessoas';

    protected static ?string $modelLabel = 'Pessoa';

    protected static ?string $pluralModelLabel = 'Pessoas';

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
                    ->maxLength(255),

                CheckboxList::make('types')
                    ->label('Tipo')
                    ->options([
                        'INCOME' => 'Receita',
                        'EXPENSE' => 'Despesa',
                    ])
                    ->columns(2)
                    ->required()
                    ->minItems(1),

                
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
            'index' => ManagePeople::route('/'),
        ];
    }
}