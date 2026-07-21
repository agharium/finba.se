<?php

namespace App\Support;

use App\Enums\Purpose;
use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Services\TitheDeliverySelection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class TitheMetrics
{
    private const TITHE_RATE = 0.10;

    public function __construct(
        private readonly string $userId,
        private readonly Carbon $month,
    ) {}

    public static function forCurrentUser(?Carbon $month = null): self
    {
        return new self((string) Auth::id(), $month ?? now());
    }

    /**
     * @return array{
     *     base_amount: float,
     *     tithe_due: float,
     *     tithe_paid: float,
     *     tithe_pending: float,
     *     tithe_balance: float,
     *     offering_target: float,
     *     offering_paid: float,
     *     offering_pending: float,
     *     firstfruits_due: float,
     *     firstfruits_paid: float,
     *     firstfruits_pending: float,
     *     firstfruits: float,
     *     combined: float,
     * }
     */
    public function summary(): array
    {
        $baseAmount = $this->eligibleIncomeForMonth();
        $titheDue = round($baseAmount * self::TITHE_RATE, 2);
        $tithePaid = $this->purposePaidInMonth(Purpose::TITHE);
        $tithePending = max(0, round($titheDue - $tithePaid, 2));

        $offeringTarget = round($baseAmount * self::TITHE_RATE, 2);
        $offeringPaid = $this->purposePaidInMonth(Purpose::OFFERING);
        $offeringPending = max(0, round($offeringTarget - $offeringPaid, 2));

        $firstfruitsDue = round($baseAmount / $this->month->daysInMonth, 2);
        $firstfruitsPaid = $this->purposePaidInMonth(Purpose::FIRSTFRUITS);
        $firstfruitsPending = max(0, round($firstfruitsDue - $firstfruitsPaid, 2));

        $combined = round($tithePending + $offeringPending + $firstfruitsPending, 2);

        return [
            'base_amount' => $baseAmount,
            'tithe_due' => $titheDue,
            'tithe_paid' => $tithePaid,
            'tithe_pending' => $tithePending,
            'tithe_balance' => $tithePending,
            'offering_target' => $offeringTarget,
            'offering_paid' => $offeringPaid,
            'offering_pending' => $offeringPending,
            'firstfruits_due' => $firstfruitsDue,
            'firstfruits_paid' => $firstfruitsPaid,
            'firstfruits_pending' => $firstfruitsPending,
            'firstfruits' => $firstfruitsPending,
            'combined' => $combined,
        ];
    }

    /**
     * @return Collection<int, Transaction>
     */
    public function eligibleIncomeTransactions(): Collection
    {
        return $this->eligibleIncomeQuery()
            ->whereYear('date', $this->month->year)
            ->whereMonth('date', $this->month->month)
            ->orderBy('date')
            ->orderBy('created_at')
            ->get();
    }

    /**
     * @return Collection<int, Transaction>
     */
    public function unlinkableEligibleIncomeTransactions(): Collection
    {
        return $this->eligibleIncomeQuery()
            ->whereYear('date', $this->month->year)
            ->whereMonth('date', $this->month->month)
            ->whereDoesntHave('titheCalculations')
            ->orderBy('date')
            ->orderBy('created_at')
            ->get();
    }

    /**
     * @param  array{
     *     tithe_pending: float,
     *     offering_pending: float,
     *     firstfruits_pending: float,
     *     combined: float,
     * }  $summary
     */
    public static function ctaLabel(array $summary): string
    {
        $pendingItems = collect([
            'dízimos' => $summary['tithe_pending'],
            'oferta complementar' => $summary['offering_pending'],
            'primícias' => $summary['firstfruits_pending'],
        ])->filter(fn (float $amount): bool => $amount > 0);

        if ($pendingItems->isEmpty()) {
            return 'Nenhum valor pendente';
        }

        if ($pendingItems->count() === 1) {
            $label = $pendingItems->keys()->first();
            $amount = $pendingItems->first();

            return sprintf('Entregar %s em %s', DashboardMetrics::formatMoney($amount), $label);
        }

        return sprintf('Entregar %s', DashboardMetrics::formatMoney($summary['combined']));
    }

    public static function ctaEnabled(array $summary): bool
    {
        return $summary['combined'] > 0;
    }

    public static function selectedTotal(array $summary, TitheDeliverySelection $selection): float
    {
        $total = 0.0;

        if ($selection->deliverTithe) {
            $total += $summary['tithe_pending'];
        }

        if ($selection->deliverOffering) {
            $total += $summary['offering_pending'];
        }

        if ($selection->deliverFirstfruits) {
            $total += $summary['firstfruits_pending'];
        }

        return round($total, 2);
    }

    private function eligibleIncomeForMonth(): float
    {
        return (float) $this->eligibleIncomeQuery()
            ->whereYear('date', $this->month->year)
            ->whereMonth('date', $this->month->month)
            ->sum('amount');
    }

    private function purposePaidInMonth(Purpose $purpose): float
    {
        return (float) Transaction::query()
            ->where('user_id', $this->userId)
            ->where('purpose', $purpose->value)
            ->whereYear('date', $this->month->year)
            ->whereMonth('date', $this->month->month)
            ->sum('amount');
    }

    private function eligibleIncomeQuery(): Builder
    {
        return Transaction::query()
            ->where('user_id', $this->userId)
            ->where('type', TransactionType::INCOME->value)
            ->whereNull('purpose');
    }
}
