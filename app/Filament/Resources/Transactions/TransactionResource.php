<?php

namespace App\Filament\Resources\Transactions;

use App\Enums\LoanStatus;
use App\Enums\LoanType;
use App\Enums\Purpose;
use App\Enums\TransactionType;
use App\Filament\Resources\Transactions\Pages\ManageTransactions;
use App\Filament\Components\MoneyInput;
use App\Models\Category;
use App\Models\City;
use App\Models\Loan;
use App\Models\Person;
use App\Models\Transaction;
use App\Support\Helpers;
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
                    ->options(TransactionType::options())
                    ->icons(TransactionType::icons())
                    ->colors(TransactionType::colors())
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
                                    ->options(TransactionType::options())
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
                                            $set('types', [TransactionType::EXPENSE->value]);
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
                        TransactionType::INCOME->value => 'Relacionar com dívida',
                        TransactionType::EXPENSE->value => 'Relacionar com empréstimo',
                        default => null,
                    })
                    ->live()
                    ->dehydrated(false)
                    ->visible(fn (callable $get): bool =>
                        (bool) Auth::user()?->is_advanced
                        && filled($get('type'))
                    ),
                
                Select::make('loan_id')
                    ->label(fn (callable $get): string => match ($get('type')) {
                        TransactionType::INCOME->value => 'Dívida',
                        TransactionType::EXPENSE->value => 'Empréstimo',
                    })
                    ->options(function (callable $get): array {
                        $loanType = match ($get('type')) {
                            TransactionType::INCOME->value => LoanType::BORROWED->value,
                            TransactionType::EXPENSE->value => LoanType::LENT->value,
                            default => null,
                        };
                
                        if (!$loanType) {
                            return [];
                        }

                        return Loan::query()
                            ->where('user_id', Auth::id())
                            ->where('status', LoanStatus::OPEN->value)
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
                    ->label(fn (callable $get): string => $get('type') === TransactionType::EXPENSE->value
                        ? 'Esta despesa é uma contribuição (dízimo ou oferta)'
                        : 'Entregar dízimo desta receita')
                    ->helperText(fn (callable $get): string => $get('type') === TransactionType::EXPENSE->value
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
                            $get('type') === TransactionType::EXPENSE->value
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
                            'sm' => 4,
                        ])
                            ->schema([
                                Grid::make(1)
                                    ->schema([
                                        TextEntry::make('description')
                                            ->label('Descrição')
                                            ->hiddenLabel()
                                            ->state(fn (Transaction $record): string => filled($record->description)
                                                ? $record->description
                                                : 'Sem descrição')
                                            ->size(TextSize::Large)
                                            ->weight(FontWeight::Bold)
                                            ->extraAttributes(fn (Transaction $record): array => [
                                                'class' => filled($record->description)
                                                    ? 'finba-transaction-view__title'
                                                    : 'finba-transaction-view__title finba-transaction-view__title--empty',
                                            ]),

                                        TextEntry::make('amount')
                                            ->label('Valor')
                                            ->hiddenLabel()
                                            ->money('BRL')
                                            ->size(TextSize::Large)
                                            ->weight(FontWeight::ExtraBold)
                                            ->extraAttributes([
                                                'class' => 'finba-transaction-view__amount',
                                            ]),
                                    ])
                                    ->columnSpan([
                                        'default' => 1,
                                        'sm' => 3,
                                    ]),

                                Grid::make(1)
                                    ->schema([
                                        TextEntry::make('type')
                                            ->label('Tipo')
                                            ->hiddenLabel()
                                            ->badge()
                                            ->formatStateUsing(fn (TransactionType|string|null $state): string => self::formatType($state))
                                            ->color(fn (TransactionType|string|null $state): string => self::typeColor($state))
                                            ->extraAttributes([
                                                'class' => 'finba-transaction-view__type',
                                            ]),

                                        TextEntry::make('date')
                                            ->label('Data')
                                            ->hiddenLabel()
                                            ->date('d/m/Y')
                                            ->extraAttributes([
                                                'class' => 'finba-transaction-view__muted',
                                            ]),
                                    ])
                                    ->extraAttributes([
                                        'class' => 'finba-transaction-view__meta',
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
                                TextEntry::make('category_path')
                                    ->label('Categoria')
                                    ->state(fn (Transaction $record): ?string => self::formatCategoryPath($record))
                                    ->visible(fn (Transaction $record): bool => filled(self::formatCategoryPath($record)))
                                    ->extraAttributes([
                                        'class' => 'finba-transaction-view__detail',
                                    ]),

                                TextEntry::make('subcategory_name')
                                    ->label('Subcategoria')
                                    ->state(fn (Transaction $record): ?string => $record->category?->parent ? $record->category->name : null)
                                    ->visible(fn (Transaction $record): bool => filled($record->category?->parent))
                                    ->extraAttributes([
                                        'class' => 'finba-transaction-view__detail',
                                    ]),

                                TextEntry::make('person.name')
                                    ->label('Pessoa')
                                    ->visible(fn (Transaction $record): bool => filled($record->person?->name))
                                    ->extraAttributes([
                                        'class' => 'finba-transaction-view__detail',
                                    ]),

                                TextEntry::make('city_display')
                                    ->label('Cidade')
                                    ->state(fn (Transaction $record): ?string => self::formatCityDisplay($record))
                                    ->visible(fn (Transaction $record): bool => filled(self::formatCityDisplay($record)))
                                    ->extraAttributes([
                                        'class' => 'finba-transaction-view__detail',
                                    ]),
                            ]),
                    ])
                    ->visible(fn (Transaction $record): bool => filled($record->person?->name)
                        || filled(self::formatCategoryPath($record))
                        || filled($record->city?->name)
                        || filled($record->category?->parent))
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
                    ->searchable(query: fn (Builder $query, string $search): Builder => Helpers::whereUnaccentedLike(
                        $query,
                        'transactions.description',
                        $search,
                    ))
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
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'category',
                        fn (Builder $query): Builder => $query
                            ->where(fn (Builder $query): Builder => Helpers::whereUnaccentedLike($query, 'name', $search))
                            ->orWhereHas(
                                'parent',
                                fn (Builder $query): Builder => Helpers::whereUnaccentedLike($query, 'name', $search),
                            ),
                    ))
                    ->visibleFrom('md'),

                TextColumn::make('city.name')
                    ->label('Cidade')
                    ->placeholder('-')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'city',
                        fn (Builder $query): Builder => Helpers::whereUnaccentedLike($query, 'name', $search),
                    ))
                    ->visibleFrom('md'),

                TextColumn::make('person.name')
                    ->label('Pessoa')
                    ->placeholder('-')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'person',
                        fn (Builder $query): Builder => Helpers::whereUnaccentedLike($query, 'name', $search),
                    ))
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
                    ->options(fn (mixed $livewire = null): array => self::availableMonthOptions(
                        Helpers::filamentFilterValue($livewire, 'year')
                    ))
                    ->native(false)
                    ->query(function (Builder $query, array $data): void {
                        if (blank($data['value'] ?? null)) {
                            return;
                        }

                        $query->whereMonth('date', (int) $data['value']);
                    }),

                SelectFilter::make('category_id')
                    ->label('Categoria')
                    ->placeholder('Todas as categorias')
                    ->options(fn (): array => self::parentCategoryOptions())
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->query(function (Builder $query, array $data): void {
                        if (blank($data['value'] ?? null)) {
                            return;
                        }

                        $categoryIds = self::categoryAndDescendantIds((string) $data['value']);

                        if ($categoryIds === []) {
                            return;
                        }

                        $query->whereIn('category_id', $categoryIds);
                    }),

                SelectFilter::make('person_id')
                    ->label('Pessoa')
                    ->placeholder('Todas as pessoas')
                    ->options(fn (): array => self::personOptions())
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->query(function (Builder $query, array $data): void {
                        if (blank($data['value'] ?? null)) {
                            return;
                        }

                        $query->where('person_id', $data['value']);
                    }),

                SelectFilter::make('city_id')
                    ->label('Cidade')
                    ->placeholder('Todas as cidades')
                    ->options(fn (): array => self::cityOptions())
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->query(function (Builder $query, array $data): void {
                        if (blank($data['value'] ?? null)) {
                            return;
                        }

                        $query->where('city_id', $data['value']);
                    }),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns([
                'default' => 1,
                'md' => 2,
                'xl' => 5,
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
        if (($data['type'] ?? null) instanceof TransactionType) {
            $data['type'] = $data['type']->value;
        }

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
        if (($data['type'] ?? null) instanceof TransactionType) {
            $data['type'] = $data['type']->value;
        }

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

    private static function formatType(TransactionType|string|null $state): string
    {
        return TransactionType::labelFor($state);
    }

    private static function typeColor(TransactionType|string|null $state): string
    {
        return TransactionType::colorFor($state);
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
            'type' => TransactionType::fromState($record->type)?->value,
            'type_icon' => TransactionType::fromState($record->type) === TransactionType::INCOME ? '+' : '-',
            'type_label' => self::formatType($record->type),
            'status' => $record->status,
            'status_label' => self::formatStatus($record->status),
        ];
    }

    private static function formatCategoryPath(Transaction $record): ?string
    {
        $category = $record->category;

        return match (true) {
            $category?->parent !== null => $category->parent->name,
            $category !== null => $category->name,
            default => null,
        };
    }

    private static function formatCityDisplay(Transaction $record): ?string
    {
        $city = $record->city;

        if (! $city) {
            return null;
        }

        return collect([$city->name, $city->region_code])
            ->filter()
            ->implode(' - ');
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

    private static function availableMonthOptions(null|int|string $year = null): array
    {
        return Transaction::query()
            ->where('user_id', Auth::id())
            ->whereNotNull('date')
            ->when(
                filled($year),
                fn (Builder $query): Builder => $query->whereYear('date', (int) $year),
            )
            ->pluck('date')
            ->map(fn ($date): int => Carbon::parse($date)->month)
            ->unique()
            ->sort()
            ->mapWithKeys(fn (int $month): array => [$month => Helpers::monthLabelPtBr($month)])
            ->all();
    }

    private static function parentCategoryOptions(): array
    {
        return Category::query()
            ->where('user_id', Auth::id())
            ->whereNull('parent_id')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private static function categoryAndDescendantIds(string $categoryId): array
    {
        $categories = Category::query()
            ->where('user_id', Auth::id())
            ->get(['id', 'parent_id']);

        if (! $categories->contains('id', $categoryId)) {
            return [];
        }

        $ids = [$categoryId];

        do {
            $added = false;

            foreach ($categories as $category) {
                if (
                    $category->parent_id
                    && in_array($category->parent_id, $ids, true)
                    && ! in_array($category->id, $ids, true)
                ) {
                    $ids[] = $category->id;
                    $added = true;
                }
            }
        } while ($added);

        return $ids;
    }

    private static function personOptions(): array
    {
        return Person::query()
            ->where('user_id', Auth::id())
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    private static function cityOptions(): array
    {
        return City::query()
            ->where('user_id', Auth::id())
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
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