<?php

namespace App\Filament\Resources\Transactions;

use App\Enums\Purpose;
use App\Filament\Resources\Transactions\Pages\ManageTransactions;
use App\Filament\Components\MoneyInput;
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
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Illuminate\Validation\Rule;
use Filament\Forms\Components\Hidden;

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
                    ->icons([
                        'INCOME' => 'heroicon-m-arrow-trending-up',
                        'EXPENSE' => 'heroicon-m-arrow-trending-down',
                    ])
                    ->colors([
                        'INCOME' => 'success',
                        'EXPENSE' => 'danger',
                    ])
                    ->inline()
                    ->grouped()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (callable $get, callable $set, ?string $state): void {
                        $parentId = $get('parent_category_id');

                        if (!$parentId || !$state) {
                            return;
                        }

                        $parent = Category::query()
                            ->where('user_id', Auth::id())
                            ->find($parentId);

                        if (!$parent || !in_array($state, $parent->types ?? [])) {
                            $set('parent_category_id', null);
                            $set('child_category_id', null);
                            $set('person_id', null); // opcional, se pessoa também depende do type
                        }
                    }),
    
                MoneyInput::make('amount')
                    ->label('Valor')
                    ->required()
                    ->minValue(0.01),
    
                TextInput::make('description')
                    ->label('Descrição'),
    
                DatePicker::make('date')
                    ->label('Data')
                    ->required()
                    ->default(now()),
    
                Select::make('parent_category_id')
                    ->label('Categoria')
                    ->options(fn (callable $get): array => Category::query()
                        ->where('user_id', Auth::id())
                        ->whereNull('parent_id')
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
                    ->dehydrated(false)
                    ->disabled(fn (callable $get): bool => blank($get('type')))
                    ->afterStateUpdated(function (callable $set, ?string $state): void {
                        $set('child_category_id', null);

                        self::applyCategoryPurpose($state, $set);
                    })
                    ->hintAction(
                        Action::make('createCategory')
                            ->label('Adicionar categoria')
                            ->icon('heroicon-m-plus')
                            ->visible(fn (callable $get): bool => filled($get('type')))
                            ->form(fn (callable $get): array => [
                                TextInput::make('name')
                                    ->label('Nome')
                                    ->required()
                                    ->maxLength(255)
                                    ->rule(fn (callable $get) => Rule::unique('categories', 'name')
                                        ->where('user_id', Auth::id())
                                        ->where(fn ($query) => $get('parent_id')
                                            ? $query->where('parent_id', $get('parent_id'))
                                            : $query->whereNull('parent_id')))
                                    ->validationMessages([
                                        'unique' => 'Você já possui uma categoria com este nome neste nível.',
                                    ]),
                    
                                Select::make('parent_id')
                                    ->label('Categoria pai')
                                    ->options(fn (): array => Category::query()
                                        ->where('user_id', Auth::id())
                                        ->whereNull('parent_id')
                                        ->whereJsonContains('types', $get('type'))
                                        ->orderBy('name')
                                        ->pluck('name', 'id')
                                        ->all())
                                    ->native(false)
                                    ->searchable()
                                    ->preload()
                                    ->nullable()
                                    ->helperText('Se selecionar uma categoria pai, a nova categoria será criada como subcategoria.'),
                    
                                CheckboxList::make('types')
                                    ->label('Tipo')
                                    ->options([
                                        'INCOME' => 'Receita',
                                        'EXPENSE' => 'Despesa',
                                    ])
                                    ->default(fn () => [$get('type')])
                                    ->columns(2)
                                    ->required()
                                    ->minItems(1)
                                    ->live()
                                    ->disabled(fn (callable $get): bool => filled($get('purpose'))),
                    
                                Select::make('purpose')
                                    ->label('Finalidade especial')
                                    ->options(Purpose::class)
                                    ->native(false)
                                    ->nullable()
                                    ->live()
                                    ->helperText('Opcional. Marque apenas categorias que representem contribuições como dízimo ou oferta. Isso impactará cálculos automáticos.')
                                    ->visible(fn (): bool => (bool) Auth::user()?->is_tither)
                                    ->afterStateUpdated(function (?Purpose $state, callable $set): void {
                                        if ($state) {
                                            $set('types', ['EXPENSE']);
                                        }
                                    }),
                            ])
                            ->action(function (array $data, callable $set): void {
                                $category = Category::create([
                                    'name' => $data['name'],
                                    'types' => $data['types'],
                                    'purpose' => $data['purpose'] ?? null,
                                    'user_id' => Auth::id(),
                                    'parent_id' => $data['parent_id'] ?? null,
                                ]);
                    
                                if (count($data['types']) === 1) {
                                    $set('type', $data['types'][0]);
                                }
                    
                                $set('purpose', $category->purpose?->value ?? $category->purpose);
                    
                                if ($category->parent_id) {
                                    $set('parent_category_id', $category->parent_id);
                                    $set('child_category_id', $category->id);
                                } else {
                                    $set('parent_category_id', $category->id);
                                    $set('child_category_id', null);
                                }
                            })
                    ),
                
                Select::make('child_category_id')
                    ->label('Subcategoria')
                    ->options(fn (callable $get): array => Category::query()
                        ->where('user_id', Auth::id())
                        ->where('parent_id', $get('parent_category_id'))
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->live()
                    ->dehydrated(false)
                    ->visible(fn (callable $get): bool => filled($get('parent_category_id'))
                        && Category::query()
                            ->where('user_id', Auth::id())
                            ->where('parent_id', $get('parent_category_id'))
                            ->exists()
                    )
                    ->afterStateUpdated(function (callable $set, ?string $state): void {
                        if (! $state) {
                            return;
                        }
                    
                        $category = Category::query()
                            ->where('user_id', Auth::id())
                            ->find($state);
                    
                        $types = $category?->types ?? [];
                    
                        if (count($types) === 1) {
                            $set('type', $types[0]);
                        }

                        self::applyCategoryPurpose($state, $set);
                    }),
    
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
    
                Toggle::make('has_loan')
                    ->columnSpanFull()
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

                Toggle::make('contribution_toggle')
                    ->columnSpanFull()
                    ->label(fn (callable $get): string => $get('type') === 'EXPENSE'
                        ? 'Esta despesa é uma contribuição (dízimo ou oferta)'
                        : 'Entregar dízimo desta receita')
                    ->helperText(fn (callable $get): string => $get('type') === 'EXPENSE'
                        ? 'Despesas marcadas como contribuição não entram novamente nos cálculos automáticos.'
                        : 'Receitas marcadas aqui serão registradas como dízimo.')
                    ->default(false)
                    ->dehydrated(false)
                    ->live()
                    ->afterStateHydrated(function (callable $set, callable $get): void {
                        $set('contribution_toggle', filled($get('purpose')));
                    })
                    ->afterStateUpdated(function (bool $state, callable $set, callable $get): void {
                        if (! $state) {
                            $set('purpose', null);
                            return;
                        }
                
                        $set(
                            'purpose',
                            $get('type') === 'EXPENSE'
                                ? Purpose::OFFERING->value
                                : Purpose::TITHE->value
                        );
                    })
                    ->visible(fn (callable $get): bool =>
                        (bool) Auth::user()?->is_tither
                        && filled($get('type'))
                    ),

                Hidden::make('purpose')
                    ->dehydrated(),
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
                EditAction::make()
                    ->mutateRecordDataUsing(function (array $data): array {
                        $category = isset($data['category_id'])
                            ? Category::find($data['category_id'])
                            : null;

                        if ($category?->parent_id) {
                            $data['parent_category_id'] = $category->parent_id;
                            $data['child_category_id'] = $category->id;
                        } else {
                            $data['parent_category_id'] = $category?->id;
                            $data['child_category_id'] = null;
                        }

                        return $data;
                    })
                    ->mutateDataUsing(function (array $data): array {
                        $data['category_id'] = $data['child_category_id']
                            ?? $data['parent_category_id']
                            ?? null;

                        unset($data['parent_category_id'], $data['child_category_id']);

                        return $data;
                    }),
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

    private static function applyCategoryPurpose(?string $categoryId, callable $set): void
    {
        if (! $categoryId) {
            $set('purpose', null);
            return;
        }

        $category = Category::query()
            ->where('user_id', Auth::id())
            ->find($categoryId);

        $set('purpose', $category?->purpose?->value ?? null);
    }
}