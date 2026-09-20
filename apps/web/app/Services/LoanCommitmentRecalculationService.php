<?php

namespace App\Services;

use App\Models\Commitment;
use App\Models\Loan;
use App\Support\InstallmentDistributor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Explicit policy for renegotiating future Loan Commitments after extra payments.
 *
 * Policy (redistributeRemainingEvenly):
 * - Never rewrite Transactions or historical allocations.
 * - Never rewrite satisfied Commitments.
 * - Soft-delete unsatisfied Commitments (including partially paid ones).
 * - Soft-deleted Commitments keep their allocation rows as historical facts;
 *   those allocations still count against the Transaction's allocated total.
 * - Recreate N new Commitments whose amounts sum exactly to the remaining Loan balance.
 *
 * Product may later offer alternate strategies (e.g. freeze partials); keep them as named methods here.
 */
class LoanCommitmentRecalculationService
{
    public function __construct(
        private LoanBalanceService $balanceService,
        private LoanCommitmentScheduleService $scheduleService,
    ) {}

    /**
     * @return Collection<int, Commitment>
     */
    public function redistributeRemainingEvenly(
        Loan $loan,
        int $installmentsCount,
        Carbon|string $firstDueDate,
    ): Collection {
        $remaining = $this->balanceService->remainingBalance($loan);

        if (bccomp($remaining, '0.00', 2) !== 1) {
            return collect();
        }

        return DB::transaction(function () use ($loan, $installmentsCount, $firstDueDate, $remaining): Collection {
            $this->softDeleteUnsatisfiedCommitments($loan);

            return $this->scheduleService->generate(
                $loan,
                $installmentsCount,
                $firstDueDate,
                $remaining,
            );
        });
    }

    private function softDeleteUnsatisfiedCommitments(Loan $loan): void
    {
        Commitment::query()
            ->where('loan_id', $loan->id)
            ->whereNull('deleted_at')
            ->orderBy('sequence')
            ->get()
            ->filter(fn (Commitment $commitment): bool => ! $commitment->isSatisfied())
            ->each(function (Commitment $commitment): void {
                $commitment->delete();
            });
    }
}
