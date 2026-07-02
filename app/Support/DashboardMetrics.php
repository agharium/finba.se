<?php

namespace App\Support;

use App\Enums\TransactionType;
use App\Filament\Resources\Transactions\TransactionResource;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class DashboardMetrics
{
    public function __construct(
        private readonly string $userId,
        private readonly Carbon $month,
    ) {}

    public static function forCurrentUser(?Carbon $month = null): self
    {
        return new self((string) Auth::id(), $month ?? now());
    }

    /**
     * @return array{income: float, expense: float, balance: float}
     */
    public function monthlyTotals(): array
    {
        $totals = Transaction::query()
            ->where('user_id', $this->userId)
            ->whereYear('date', $this->month->year)
            ->whereMonth('date', $this->month->month)
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'INCOME' AND purpose IS NULL THEN amount ELSE 0 END), 0) as income")
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'EXPENSE' AND purpose IS NULL THEN amount ELSE 0 END), 0) as expense")
            ->first();

        $income = (float) $totals->income;
        $expense = (float) $totals->expense;

        return [
            'income' => $income,
            'expense' => $expense,
            'balance' => $income - $expense,
        ];
    }

    /**
     * @return Collection<int, Transaction>
     */
    public function recentTransactions(int $limit = 3): Collection
    {
        return Transaction::query()
            ->where('user_id', $this->userId)
            ->whereYear('date', $this->month->year)
            ->whereMonth('date', $this->month->month)
            ->with(['category.parent'])
            ->orderByDesc('date')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get(['id', 'description', 'amount', 'type', 'date', 'category_id']);
    }

    /**
     * @return Collection<int, object{category_name: string, total: string|float}>
     */
    public function topExpenseCategories(int $limit = 5): Collection
    {
        return Transaction::query()
            ->where('transactions.user_id', $this->userId)
            ->where('transactions.type', TransactionType::EXPENSE->value)
            ->whereNull('transactions.purpose')
            ->whereYear('transactions.date', $this->month->year)
            ->whereMonth('transactions.date', $this->month->month)
            ->whereNotNull('transactions.category_id')
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->leftJoin('categories as parent_categories', 'categories.parent_id', '=', 'parent_categories.id')
            ->selectRaw('COALESCE(parent_categories.name, categories.name) as category_name')
            ->selectRaw('SUM(transactions.amount) as total')
            ->groupByRaw('COALESCE(parent_categories.id, categories.id), COALESCE(parent_categories.name, categories.name)')
            ->havingRaw('SUM(transactions.amount) > 0')
            ->orderByDesc('total')
            ->limit($limit)
            ->get();
    }

    public function monthLabel(): string
    {
        return Helpers::monthLabelPtBr($this->month->month) . ' ' . $this->month->year;
    }

    /**
     * @return array<int, string>
     */
    public static function availableYearOptions(): array
    {
        $years = Transaction::query()
            ->where('user_id', Auth::id())
            ->whereNotNull('date')
            ->pluck('date')
            ->map(fn ($date): int => Carbon::parse($date)->year)
            ->unique()
            ->sortDesc()
            ->values();

        if (! $years->contains(now()->year)) {
            $years->prepend(now()->year);
        }

        return $years
            ->unique()
            ->sortDesc()
            ->mapWithKeys(fn (int $year): array => [$year => (string) $year])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function availableMonthOptions(null|int|string $year = null): array
    {
        $year = filled($year) ? (int) $year : now()->year;

        $months = Transaction::query()
            ->where('user_id', Auth::id())
            ->whereNotNull('date')
            ->whereYear('date', $year)
            ->pluck('date')
            ->map(fn ($date): int => Carbon::parse($date)->month)
            ->unique()
            ->sort()
            ->values();

        if ($year === now()->year && ! $months->contains(now()->month)) {
            $months->push(now()->month);
        }

        return $months
            ->unique()
            ->sort()
            ->mapWithKeys(fn (int $month): array => [$month => Helpers::monthLabelPtBr($month)])
            ->all();
    }

    public static function transactionsUrl(string $tab, int $year, int $month): string
    {
        return TransactionResource::getUrl('index') . '?' . http_build_query([
            'tab' => $tab,
            'filters' => [
                'year' => ['value' => (string) $year],
                'month' => ['value' => (string) $month],
            ],
        ]);
    }

    public static function formatBrl(float|int|string|null $amount): string
    {
        return 'R$ ' . number_format((float) ($amount ?? 0), 2, ',', '.');
    }

    public static function categoryDisplayName(Transaction $transaction): ?string
    {
        $category = $transaction->category;

        if (! $category) {
            return null;
        }

        if ($category->parent) {
            return $category->parent->name . ' • ' . $category->name;
        }

        return $category->name;
    }

    public static function transactionDisplayTitle(Transaction $transaction): string
    {
        return filled($transaction->description) ? $transaction->description : 'Sem descrição';
    }
}
