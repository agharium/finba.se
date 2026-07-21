<?php

namespace App\Filament\Resources\Loans;

use App\Enums\LoanStatus;
use App\Enums\LoanType;
use App\Enums\TransactionType;
use App\Filament\Components\MoneyInput;
use App\Filament\Resources\Loans\Pages\ManageLoans;
use App\Models\Category;
use App\Models\Loan;
use App\Models\Person;
use App\Services\ReceivablePaymentService;
use App\Support\Helpers;
use App\Support\MoneyFormatter;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class LoanResource extends Resource
{
    protected static ?string $model = Loan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Contas a receber';

    protected static ?string $modelLabel = 'Conta a receber';

    protected static ?string $pluralModelLabel = 'Contas a receber';

    protected static ?string $recordTitleAttribute = 'description';

    protected static ?int $navigationSort = 15;

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) auth()->user()?->usesAccountsReceivable();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ])
            ->with(['person'])
            ->where('user_id', Auth::id());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultCurrency(fn (): string => MoneyFormatter::currencyCode())
            ->defaultNumberLocale(fn (): string => MoneyFormatter::numberLocale())
            ->recordTitleAttribute('description')
            ->defaultSort('created_at', 'desc')
            ->columns([
                ViewColumn::make('mobile_card')
                    ->label('')
                    ->view('filament.resources.loans.mobile-card')
                    ->extraCellAttributes([
                        'class' => 'finba-mobile-card-cell',
                    ]),

                TextColumn::make('description')
                    ->label('Descrição')
                    ->placeholder('Conta a receber')
                    ->searchable(query: fn (Builder $query, string $search): Builder => Helpers::whereUnaccentedLike(
                        $query,
                        'loans.description',
                        $search,
                    ))
                    ->limit(40)
                    ->visibleFrom('md'),

                TextColumn::make('person.name')
                    ->label('Cliente')
                    ->placeholder('-')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'person',
                        fn (Builder $query): Builder => Helpers::whereUnaccentedLike($query, 'name', $search),
                    ))
                    ->visibleFrom('md'),

                TextColumn::make('original_amount')
                    ->label('Original')
                    ->money()
                    ->visibleFrom('md'),

                TextColumn::make('received_amount')
                    ->label('Recebido')
                    ->state(fn (Loan $record): string => app(ReceivablePaymentService::class)->paidAmountFor($record))
                    ->money()
                    ->visibleFrom('md'),

                TextColumn::make('remaining_amount')
                    ->label('Falta receber')
                    ->state(fn (Loan $record): string => app(ReceivablePaymentService::class)->remainingBalanceFor($record))
                    ->money()
                    ->weight('bold')
                    ->color('warning')
                    ->visibleFrom('md'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (LoanStatus $state): string => $state->getLabel())
                    ->color(fn (LoanStatus $state): string => match ($state) {
                        LoanStatus::OPEN => 'warning',
                        LoanStatus::CLOSED => 'success',
                    })
                    ->visibleFrom('md'),

                TextColumn::make('created_at')
                    ->label('Criada em')
                    ->date('d/m/Y')
                    ->sortable()
                    ->visibleFrom('lg'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(collect(LoanStatus::cases())
                        ->mapWithKeys(fn (LoanStatus $status): array => [$status->value => $status->getLabel()])
                        ->all())
                    ->default(LoanStatus::OPEN->value)
                    ->native(false),

                SelectFilter::make('person_id')
                    ->label('Cliente')
                    ->placeholder('Todos os clientes')
                    ->options(fn (): array => self::personOptions())
                    ->searchable()
                    ->preload()
                    ->native(false),

                SelectFilter::make('year')
                    ->label('Ano')
                    ->placeholder('Todos os anos')
                    ->options(fn (): array => self::availableYearOptions())
                    ->native(false)
                    ->query(function (Builder $query, array $data): void {
                        if (blank($data['value'] ?? null)) {
                            return;
                        }

                        $query->whereYear('created_at', (int) $data['value']);
                    }),

                SelectFilter::make('month')
                    ->label('Mês')
                    ->placeholder('Todos os meses')
                    ->options(fn (mixed $livewire = null): array => self::availableMonthOptions(
                        Helpers::filamentFilterValue($livewire, 'year'),
                    ))
                    ->native(false)
                    ->query(function (Builder $query, array $data): void {
                        if (blank($data['value'] ?? null)) {
                            return;
                        }

                        $query->whereMonth('created_at', (int) $data['value']);
                    }),

                SelectFilter::make('type')
                    ->label('Tipo')
                    ->options(collect(LoanType::cases())
                        ->mapWithKeys(fn (LoanType $type): array => [$type->value => $type->getLabel()])
                        ->all())
                    ->default(LoanType::RECEIVABLE->value)
                    ->native(false),
            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns([
                'default' => 1,
                'md' => 2,
                'xl' => 5,
            ])
            ->deferFilters(false)
            ->recordActions([
                self::registerPaymentAction(),
            ]);
    }

    public static function registerPaymentAction(string $name = 'registerPayment'): Action
    {
        return Action::make($name)
            ->label('Registrar pagamento')
            ->icon('heroicon-m-banknotes')
            ->color('success')
            ->button()
            ->visible(fn (Loan $record): bool => self::canRegisterPayment($record))
            ->modalHeading('Registrar pagamento')
            ->modalSubmitActionLabel('Confirmar')
            ->fillForm(fn (Loan $record): array => [
                'amount' => app(ReceivablePaymentService::class)->remainingBalanceFor($record),
                'date' => now()->toDateString(),
            ])
            ->schema(fn (Loan $record): array => self::paymentFormSchema($record))
            ->action(function (Loan $record, array $data): void {
                app(ReceivablePaymentService::class)->registerPayment(
                    auth()->user(),
                    $record,
                    $data,
                );

                Notification::make()
                    ->title('Pagamento registrado com sucesso.')
                    ->success()
                    ->send();
            });
    }

    /**
     * @return array<int, mixed>
     */
    public static function paymentFormSchema(Loan $record): array
    {
        $summary = app(ReceivablePaymentService::class)->summaryFor($record);

        return [
            TextInput::make('_customer')
                ->label('Cliente')
                ->default($record->person?->name ?? '-')
                ->disabled()
                ->dehydrated(false),

            TextInput::make('_description')
                ->label('Descrição')
                ->default($record->description ?? 'Conta a receber')
                ->disabled()
                ->dehydrated(false),

            TextInput::make('_original')
                ->label('Valor original')
                ->default($summary['original'])
                ->disabled()
                ->dehydrated(false),

            TextInput::make('_received')
                ->label('Já recebido')
                ->default($summary['received'])
                ->disabled()
                ->dehydrated(false),

            TextInput::make('_remaining')
                ->label('Falta receber')
                ->default($summary['remaining'])
                ->disabled()
                ->dehydrated(false),

            MoneyInput::make('amount')
                ->label('Valor do pagamento')
                ->required()
                ->minValue(0.01),

            DatePicker::make('date')
                ->label('Data do pagamento')
                ->required()
                ->default(now()),

            Select::make('category_id')
                ->label('Categoria')
                ->options(fn (): array => self::incomeCategoryOptions())
                ->searchable()
                ->preload()
                ->native(false)
                ->nullable(),

            Textarea::make('description')
                ->label('Descrição do pagamento')
                ->rows(2)
                ->nullable(),
        ];
    }

    /**
     * @return array{
     *     description: string,
     *     customer: ?string,
     *     created_at: ?string,
     *     original: string,
     *     received: string,
     *     remaining: string,
     *     status_label: string,
     *     is_open: bool,
     * }
     */
    public static function mobileCardData(Loan $record): array
    {
        $summary = app(ReceivablePaymentService::class)->summaryFor($record);

        return [
            'description' => filled($record->description) ? $record->description : 'Conta a receber',
            'customer' => $record->person?->name,
            'created_at' => $record->created_at?->format('d/m/Y'),
            'original' => $summary['original'],
            'received' => $summary['received'],
            'remaining' => $summary['remaining'],
            'status_label' => $record->status->getLabel(),
            'is_open' => $record->status === LoanStatus::OPEN,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageLoans::route('/'),
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

    private static function canRegisterPayment(Loan $record): bool
    {
        return $record->type === LoanType::RECEIVABLE
            && $record->status === LoanStatus::OPEN;
    }

    private static function personOptions(): array
    {
        return Person::query()
            ->where('user_id', Auth::id())
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    private static function incomeCategoryOptions(): array
    {
        return Category::query()
            ->where('user_id', Auth::id())
            ->whereJsonContains('types', TransactionType::INCOME->value)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private static function availableYearOptions(): array
    {
        return Loan::query()
            ->where('user_id', Auth::id())
            ->whereNotNull('created_at')
            ->pluck('created_at')
            ->map(fn ($date): int => Carbon::parse($date)->year)
            ->unique()
            ->sortDesc()
            ->mapWithKeys(fn (int $year): array => [$year => (string) $year])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private static function availableMonthOptions(null|int|string $year = null): array
    {
        return Loan::query()
            ->where('user_id', Auth::id())
            ->whereNotNull('created_at')
            ->when(
                filled($year),
                fn (Builder $query): Builder => $query->whereYear('created_at', (int) $year),
            )
            ->pluck('created_at')
            ->map(fn ($date): int => Carbon::parse($date)->month)
            ->unique()
            ->sort()
            ->mapWithKeys(fn (int $month): array => [$month => Helpers::monthLabelPtBr($month)])
            ->all();
    }
}
