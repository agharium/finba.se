<?php

namespace App\Filament\Resources\Transactions;

use App\Filament\Resources\Transactions\Pages\ManageTransactions;
use App\Models\Category;
use App\Models\Loan;
use App\Models\Person;
use App\Models\Transaction;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Transações';

    protected static ?string $modelLabel = 'Transação';

    protected static ?string $pluralModelLabel = 'Transações';

    protected static ?string $recordTitleAttribute = 'description';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('user_id', Auth::id());
    }

    public static function form(Schema $schema): Schema
    {
        $user = Auth::user();
    
        return $schema
            ->components([
                ToggleButtons::make('type')
                    ->label('Tipo')
                    ->options([
                        'INCOME' => 'Receita',
                        'EXPENSE' => 'Despesa',
                    ])
                    ->required()
                    ->live()
                    ->inline()
                    ->colors([
                        'INCOME' => 'success',
                        'EXPENSE' => 'danger',
                    ])
                    ->icons([
                        'INCOME' => 'heroicon-m-arrow-trending-up',
                        'EXPENSE' => 'heroicon-m-arrow-trending-down',
                    ]),
    
                TextInput::make('amount')
                    ->label('Valor')
                    ->required()
                    ->numeric()
                    ->prefix('R$')
                    ->minValue(0.01),
    
                DatePicker::make('date')
                    ->label('Data')
                    ->required()
                    ->default(now()),
    
                Select::make('person_id')
                    ->label('Pessoa')
                    ->options(fn (callable $get): array => Person::query()
                        ->where('user_id', Auth::id())
                        ->when(
                            $get('type'),
                            fn (Builder $query, string $type) => $query->whereJsonContains('types', $type),
                        )
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->live()
                    ->visible(fn (): bool => (bool) Auth::user()?->is_advanced)
                    ->disabled(fn (callable $get): bool => blank($get('type')))
                    ->helperText(fn (callable $get): ?string => blank($get('type'))
                        ? 'Selecione o tipo para carregar as pessoas compatíveis.'
                        : null),
    
                Select::make('category_id')
                    ->label('Categoria')
                    ->options(function (callable $get): array {
                        $query = Category::query()
                            ->where('user_id', Auth::id());
    
                        if ($type = $get('type')) {
                            $query->whereJsonContains('types', $type);
                        }
    
                        if (
                            Auth::user()?->is_advanced
                            && filled($get('person_id'))
                        ) {
                            $person = Person::query()
                                ->where('user_id', Auth::id())
                                ->find($get('person_id'));
    
                            if ($person && $person->categories()->exists()) {
                                $query->whereIn(
                                    'id',
                                    $person->categories()->pluck('categories.id')
                                );
                            }
                        }
    
                        return $query
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all();
                    })
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->disabled(fn (callable $get): bool => blank($get('type')))
                    ->helperText(fn (callable $get): ?string => blank($get('type'))
                        ? 'Selecione o tipo para carregar as categorias compatíveis.'
                        : null),
    
                Toggle::make('is_titheable')
                    ->label(fn (callable $get): string => $get('type') === 'EXPENSE'
                        ? 'Descontar esta despesa no cálculo de dízimos'
                        : 'Entregar dízimos deste provento')
                    ->helperText(fn (callable $get): string => $get('type') === 'EXPENSE'
                        ? 'Esta despesa será considerada na base de cálculo.'
                        : 'Esta receita entrará na base de cálculo dos dízimos, ofertas e primícias.')
                    ->default(false)
                    ->visible(fn (callable $get): bool => (bool) Auth::user()?->is_tither && filled($get('type'))),
    
                    Toggle::make('has_loan')
                    ->label(fn (callable $get): string => match ($get('type')) {
                        'INCOME' => 'Relacionar com empréstimo',
                        'EXPENSE' => 'Relacionar com dívida',
                        default => 'Relacionar',
                    })
                    ->live()
                    ->dehydrated(false)
                    ->visible(fn (callable $get): bool =>
                        (bool) Auth::user()?->is_advanced
                        && filled($get('type'))
                    ),
                
                Select::make('loan_id')
                    ->label(fn (callable $get): string => match ($get('type')) {
                        'INCOME' => 'Empréstimo',
                        'EXPENSE' => 'Dívida',
                        default => 'Relacionamento financeiro',
                    })
                    ->options(function (callable $get): array {
                        $loanType = match ($get('type')) {
                            'INCOME' => 'EXPENSE',
                            'EXPENSE' => 'INCOME',
                            default => null,
                        };
                
                        if (!$loanType) {
                            return [];
                        }
                
                        return Loan::query()
                            ->where('user_id', Auth::id())
                            ->where('status', 'OPEN')
                            ->where('type', $loanType)
                            ->orderBy('description')
                            ->pluck('description', 'id')
                            ->all();
                    })
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->visible(fn (callable $get): bool =>
                        (bool) Auth::user()?->is_advanced
                        && filled($get('type'))
                        && (bool) $get('has_loan')
                    ),
    
                TextInput::make('description')
                    ->label('Descrição')
                    ->columnSpanFull(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('description')
                    ->label('Descrição')
                    ->placeholder('-')
                    ->columnSpanFull(),

                TextEntry::make('amount')
                    ->label('Valor')
                    ->money('BRL'),

                TextEntry::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => self::formatType($state))
                    ->color(fn (?string $state): string => self::typeColor($state)),

                TextEntry::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => self::formatStatus($state))
                    ->color(fn (?string $state): string => self::statusColor($state)),

                TextEntry::make('date')
                    ->label('Data')
                    ->date(),

                TextEntry::make('category.name')
                    ->label('Categoria')
                    ->placeholder('-'),

                TextEntry::make('person.name')
                    ->label('Pessoa')
                    ->placeholder('-'),

                TextEntry::make('loan.description')
                    ->label('Empréstimo / dívida')
                    ->placeholder('-'),

                TextEntry::make('installment_number')
                    ->label('Parcela')
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
            ->recordTitleAttribute('description')
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('date')
                    ->label('Data')
                    ->date()
                    ->sortable(),

                TextColumn::make('description')
                    ->label('Descrição')
                    ->placeholder('-')
                    ->searchable()
                    ->limit(40),

                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => self::formatType($state))
                    ->color(fn (?string $state): string => self::typeColor($state))
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => self::formatStatus($state))
                    ->color(fn (?string $state): string => self::statusColor($state))
                    ->sortable(),

                TextColumn::make('amount')
                    ->label('Valor')
                    ->money('BRL')
                    ->sortable(),

                TextColumn::make('category.name')
                    ->label('Categoria')
                    ->placeholder('-')
                    ->searchable(),

                TextColumn::make('person.name')
                    ->label('Pessoa')
                    ->placeholder('-')
                    ->searchable(),

                TextColumn::make('loan.description')
                    ->label('Empréstimo / dívida')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('installment_number')
                    ->label('Parcela')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

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
                    ->modalHeading('Excluir transação')
                    ->modalDescription('Tem certeza que deseja excluir esta transação?'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->requiresConfirmation(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTransactions::route('/'),
        ];
    }

    private static function formatType(?string $state): string
    {
        return match ($state) {
            'INCOME' => 'Receita',
            'EXPENSE' => 'Despesa',
            default => $state ?? '-',
        };
    }

    private static function typeColor(?string $state): string
    {
        return match ($state) {
            'INCOME' => 'success',
            'EXPENSE' => 'danger',
            default => 'gray',
        };
    }

    private static function formatStatus(?string $state): string
    {
        return match ($state) {
            'PAID' => 'Pago',
            'PENDING' => 'Pendente',
            'CANCELED' => 'Cancelado',
            default => $state ?? '-',
        };
    }

    private static function statusColor(?string $state): string
    {
        return match ($state) {
            'PAID' => 'success',
            'PENDING' => 'warning',
            'CANCELED' => 'gray',
            default => 'gray',
        };
    }
}