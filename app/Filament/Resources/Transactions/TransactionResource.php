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
use Filament\Actions\ActionGroup;
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
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Support\Enums\TextSize;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Illuminate\Validation\Rule;
use Filament\Forms\Components\Hidden;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWallet;

    protected static ?string $navigationLabel = 'Transações';

    protected static ?string $modelLabel = 'Transação';

    protected static ?string $pluralModelLabel = 'Transações';

    protected static ?string $recordTitleAttribute = 'description';

    protected static ?int $navigationSort = 10;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['category.parent', 'person', 'city', 'loan'])
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
                            $set('category_id', null);
                            $set('person_id', null); // opcional, se pessoa também depende do type
                            $set('purpose', null);
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
                        : null)
                    ->live()
                    ->afterStateUpdated(function (?string $state, callable $set): void {
                        $set('city_id', null);
                    
                        if (! $state) {
                            return;
                        }
                    
                        $cityIds = Person::query()
                            ->where('user_id', Auth::id())
                            ->whereKey($state)
                            ->first()
                            ?->cities()
                            ->pluck('cities.id')
                            ->all() ?? [];
                    
                        if (count($cityIds) === 1) {
                            $set('city_id', $cityIds[0]);
                        }
                    }),

                Select::make('city_id')
                    ->label('Cidade')
                    ->relationship(
                        name: 'city',
                        titleAttribute: 'name',
                        modifyQueryUsing: function (Builder $query, callable $get) {
                            $query->where('cities.user_id', Auth::id());
                
                            $personId = $get('person_id');
                
                            if ($personId) {
                                $query->whereHas('people', fn (Builder $query) => $query->whereKey($personId));
                            }
                
                            return $query->orderBy('cities.name');
                        },
                    )
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->live()
                    ->visible(fn (): bool => (bool) Auth::user()?->is_advanced)
                    ->nullable(),
    
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
                        $set('category_id', $state);

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
                                    $set('category_id', $category->id);
                                } else {
                                    $set('parent_category_id', $category->id);
                                    $set('child_category_id', null);
                                    $set('category_id', $category->id);
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
                    ->afterStateUpdated(function (callable $get, callable $set, ?string $state): void {
                        $categoryId = $state ?: $get('parent_category_id');

                        $set('category_id', $categoryId);

                        if (! $state) {
                            self::applyCategoryPurpose($categoryId, $set);

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
    
                Toggle::make('has_loan')
                    ->columnSpanFull(fn (callable $get): bool => !filled($get('parent_category_id')))
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

                Hidden::make('category_id')
                    ->dehydrated(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'md' => 3,
                        ])
                            ->schema([
                                Grid::make([
                                    'default' => 1,
                                    'sm' => 4,
                                ])
                                    ->schema([
                                        TextEntry::make('description')
                                            ->label('Descrição')
                                            ->hiddenLabel()
                                            ->placeholder('-')
                                            ->size(TextSize::Large)
                                            ->weight(FontWeight::Bold)
                                            ->extraAttributes([
                                                'class' => 'finba-transaction-view__title',
                                            ])
                                            ->columnSpan([
                                                'default' => 1,
                                                'sm' => 3,
                                            ]),

                                        TextEntry::make('type')
                                            ->label('Tipo')
                                            ->hiddenLabel()
                                            ->badge()
                                            ->formatStateUsing(fn (?string $state): string => self::formatType($state))
                                            ->color(fn (?string $state): string => self::typeColor($state))
                                            ->extraAttributes([
                                                'class' => 'finba-transaction-view__type',
                                            ]),
                                    ])
                                    ->columnSpan([
                                        'default' => 1,
                                        'md' => 2,
                                    ]),

                                Grid::make(1)
                                    ->schema([
                                        TextEntry::make('amount')
                                            ->label('Valor')
                                            ->hiddenLabel()
                                            ->money('BRL')
                                            ->size(TextSize::Large)
                                            ->weight(FontWeight::ExtraBold)
                                            ->extraAttributes([
                                                'class' => 'finba-transaction-view__amount',
                                            ]),

                                        Grid::make(2)
                                            ->schema([
                                                TextEntry::make('status')
                                                    ->label('Status')
                                                    ->badge()
                                                    ->formatStateUsing(fn (?string $state): string => self::formatStatus($state))
                                                    ->color(fn (?string $state): string => self::statusColor($state)),

                                                TextEntry::make('date')
                                                    ->label('Data')
                                                    ->date()
                                                    ->extraAttributes([
                                                        'class' => 'finba-transaction-view__muted',
                                                    ]),
                                            ]),
                                    ]),
                            ]),
                    ])
                    ->extraAttributes([
                        'class' => 'finba-transaction-view finba-transaction-view__hero',
                    ])
                    ->columnSpanFull(),

                Section::make('Detalhes')
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'md' => 2,
                        ])
                            ->schema([
                                TextEntry::make('person.name')
                                    ->label('Pessoa')
                                    ->placeholder('-')
                                    ->extraAttributes([
                                        'class' => 'finba-transaction-view__detail',
                                    ]),

                                TextEntry::make('category_path')
                                    ->label('Categoria')
                                    ->state(fn (Transaction $record): string => self::formatCategoryPath($record))
                                    ->extraAttributes([
                                        'class' => 'finba-transaction-view__detail',
                                    ]),

                                TextEntry::make('loan.description')
                                    ->label('Empréstimo / dívida')
                                    ->placeholder('-')
                                    ->extraAttributes([
                                        'class' => 'finba-transaction-view__detail',
                                    ]),

                                TextEntry::make('installment_number')
                                    ->label('Parcela')
                                    ->placeholder('-')
                                    ->extraAttributes([
                                        'class' => 'finba-transaction-view__detail',
                                    ]),

                                TextEntry::make('created_at')
                                    ->label('Criado em')
                                    ->dateTime()
                                    ->placeholder('-')
                                    ->extraAttributes([
                                        'class' => 'finba-transaction-view__detail finba-transaction-view__muted',
                                    ]),

                                TextEntry::make('updated_at')
                                    ->label('Atualizado em')
                                    ->dateTime()
                                    ->placeholder('-')
                                    ->extraAttributes([
                                        'class' => 'finba-transaction-view__detail finba-transaction-view__muted',
                                    ]),
                            ]),
                    ])
                    ->extraAttributes([
                        'class' => 'finba-transaction-view finba-transaction-view__details',
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->defaultSort('date', 'desc')
            ->columns([
                ViewColumn::make('mobile_card')
                    ->label('')
                    ->view('filament.resources.transactions.mobile-card')
                    ->extraCellAttributes([
                        'class' => 'finba-mobile-card-cell',
                    ]),

                // TextColumn::make('date')
                //     ->label('Data')
                //     ->date()
                //     ->sortable()
                //     ->visibleFrom('md'),

                TextColumn::make('description')
                    ->label('Descrição')
                    ->placeholder('-')
                    ->searchable()
                    ->limit(40)
                    ->visibleFrom('md'),

                // TextColumn::make('type')
                //     ->label('Tipo')
                //     ->badge()
                //     ->formatStateUsing(fn (?string $state): string => self::formatType($state))
                //     ->color(fn (?string $state): string => self::typeColor($state))
                //     ->sortable(),

                // TextColumn::make('status')
                //     ->label('Status')
                //     ->badge()
                //     ->formatStateUsing(fn (?string $state): string => self::formatStatus($state))
                //     ->color(fn (?string $state): string => self::statusColor($state))
                //     ->sortable(),

                // TextColumn::make('amount')
                //     ->label('Valor')
                //     ->money('BRL')
                //     ->sortable()
                //     ->visibleFrom('md'),

                TextColumn::make('category.name')
                    ->label('Categoria')
                    ->placeholder('-')
                    ->searchable()
                    ->visibleFrom('md'),

                TextColumn::make('city.name')
                    ->label('Cidade')
                    ->placeholder('-')
                    ->searchable()
                    ->visibleFrom('md'),

                TextColumn::make('person.name')
                    ->label('Pessoa')
                    ->placeholder('-')
                    ->searchable()
                    ->visibleFrom('md'),

                // TextColumn::make('loan.description')
                //     ->label('Empréstimo / dívida')
                //     ->placeholder('-')
                //     ->toggleable(isToggledHiddenByDefault: true)
                //     ->visibleFrom('md'),

                // TextColumn::make('installment_number')
                //     ->label('Parcela')
                //     ->placeholder('-')
                //     ->toggleable(isToggledHiddenByDefault: true)
                //     ->visibleFrom('md'),

                // TextColumn::make('created_at')
                //     ->label('Criado em')
                //     ->dateTime()
                //     ->sortable()
                //     ->toggleable(isToggledHiddenByDefault: true),

                // TextColumn::make('updated_at')
                //     ->label('Atualizado em')
                //     ->dateTime()
                //     ->sortable()
                //     ->toggleable(isToggledHiddenByDefault: true)
                //     ->visibleFrom('md'),
            ])
            ->filters([
                SelectFilter::make('year')
                    ->label('Ano')
                    ->placeholder('Todos os anos')
                    ->options(fn (): array => self::availableYearOptions())
                    ->native(false)
                    ->query(function (Builder $query, array $data): void {
                        if (blank($data['value'] ?? null)) {
                            return;
                        }

                        $query->whereYear('date', (int) $data['value']);
                    }),

                SelectFilter::make('month')
                    ->label('Mês')
                    ->placeholder('Todos os meses')
                    ->options(fn (): array => self::availableMonthOptions())
                    ->native(false)
                    ->query(function (Builder $query, array $data): void {
                        if (blank($data['value'] ?? null)) {
                            return;
                        }

                        $query->whereMonth('date', (int) $data['value']);
                    }),
            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns([
                'default' => 1,
                'md' => 2,
            ])
            ->deferFilters(false)
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make('mobileView')
                        ->label('Visualizar'),
                    EditAction::make('mobileEdit')
                        ->label('Editar')
                        ->mutateRecordDataUsing(fn (array $data): array => self::mutateTransactionRecordDataForForm($data))
                        ->mutateDataUsing(fn (array $data): array => self::mutateTransactionFormDataForSave($data)),
                    DeleteAction::make('mobileDelete')
                        ->label('Excluir')
                        ->requiresConfirmation()
                        ->modalHeading('Excluir transação')
                        ->modalDescription('Tem certeza que deseja excluir esta transação?'),
                ])
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->label('Ações')
                    ->color('gray')
                    ->iconButton()
                    ->extraAttributes([
                        'class' => 'finba-mobile-actions-menu',
                    ]),
                ViewAction::make()
                    ->extraAttributes([
                        'class' => 'finba-desktop-record-action',
                    ]),
                EditAction::make()
                    ->extraAttributes([
                        'class' => 'finba-desktop-record-action',
                    ])
                    ->mutateRecordDataUsing(fn (array $data): array => self::mutateTransactionRecordDataForForm($data))
                    ->mutateDataUsing(fn (array $data): array => self::mutateTransactionFormDataForSave($data)),
                DeleteAction::make()
                    ->extraAttributes([
                        'class' => 'finba-desktop-record-action',
                    ])
                    ->requiresConfirmation()
                    ->modalHeading('Excluir transação')
                    ->modalDescription('Tem certeza que deseja excluir esta transação?'),
            ]);

            // ->toolbarActions([
            //     BulkActionGroup::make([
            //         DeleteBulkAction::make()
            //             ->requiresConfirmation(),
            //     ]),
            // ]);
    }

    private static function mutateTransactionRecordDataForForm(array $data): array
    {
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
    }

    public static function mutateTransactionFormDataForSave(array $data): array
    {
        $data['category_id'] = $data['child_category_id']
            ?? $data['parent_category_id']
            ?? $data['category_id']
            ?? null;

        $data['category_id'] = filled($data['category_id'])
            ? $data['category_id']
            : null;

        unset($data['parent_category_id'], $data['child_category_id']);

        return $data;
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

    /**
     * @return array{
     *     description: string,
     *     amount: string,
     *     date: string,
     *     category: string,
     *     category_path: string,
     *     person: ?string,
     *     counterparty: string,
     *     purpose: ?string,
     *     type: ?string,
     *     type_icon: string,
     *     type_label: string,
     *     status: ?string,
     *     status_label: string
     * }
     */
    public static function mobileCardData(Transaction $record): array
    {
        $person = $record->person?->name;
        $category = $record->category;
        $categoryPath = self::formatCategoryPath($record);

        return [
            'description' => filled($record->description) ? $record->description : null,
            'amount' => 'R$ ' . number_format((float) $record->amount, 2, ',', '.'),
            'date' => $record->date?->format('d/m/Y'),
            'category' => $category?->name,
            'category_path' => $categoryPath,
            'person' => $person,
            'city' => $record->city?->name,
            'counterparty' => collect([$person, $categoryPath])
                ->filter()
                ->implode(' • ') ?: '-',
            'purpose' => self::formatPurpose($record->purpose),
            'type' => $record->type,
            'type_icon' => $record->type === 'INCOME' ? '+' : '-',
            'type_label' => self::formatType($record->type),
            'status' => $record->status,
            'status_label' => self::formatStatus($record->status),
        ];
    }

    private static function formatCategoryPath(Transaction $record): ?string
    {
        $category = $record->category;

        return match (true) {
            $category?->parent !== null => "{$category->parent->name} • {$category->name}",
            $category !== null => $category->name,
            default => null,
        };
    }

    private static function availableYearOptions(): array
    {
        return Transaction::query()
            ->where('user_id', Auth::id())
            ->whereNotNull('date')
            ->pluck('date')
            ->map(fn ($date): int => Carbon::parse($date)->year)
            ->unique()
            ->sortDesc()
            ->mapWithKeys(fn (int $year): array => [$year => (string) $year])
            ->all();
    }

    private static function availableMonthOptions(): array
    {
        return Transaction::query()
            ->where('user_id', Auth::id())
            ->whereNotNull('date')
            ->pluck('date')
            ->map(fn ($date): int => Carbon::parse($date)->month)
            ->unique()
            ->sort()
            ->mapWithKeys(fn (int $month): array => [$month => self::monthLabel($month)])
            ->all();
    }

    private static function monthLabel(int $month): string
    {
        return [
            1 => 'Janeiro',
            2 => 'Fevereiro',
            3 => 'Março',
            4 => 'Abril',
            5 => 'Maio',
            6 => 'Junho',
            7 => 'Julho',
            8 => 'Agosto',
            9 => 'Setembro',
            10 => 'Outubro',
            11 => 'Novembro',
            12 => 'Dezembro',
        ][$month] ?? (string) $month;
    }

    private static function formatPurpose(Purpose|string|null $purpose): ?string
    {
        if ($purpose instanceof Purpose) {
            return $purpose->getLabel();
        }

        return match ($purpose) {
            Purpose::TITHE->value => Purpose::TITHE->getLabel(),
            Purpose::OFFERING->value => Purpose::OFFERING->getLabel(),
            default => null,
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