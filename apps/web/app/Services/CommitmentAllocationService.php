<?php

namespace App\Services;

use App\Exceptions\CommitmentAllocationException;
use App\Models\Commitment;
use App\Models\Transaction;
use App\Support\InstallmentDistributor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CommitmentAllocationService
{
    /**
     * Allocate part of a real Transaction toward satisfying a Commitment.
     * Never mutates the Transaction amount.
     */
    public function allocate(Commitment $commitment, Transaction $transaction, string|float|int $amount): void
    {
        $formatted = InstallmentDistributor::formatAmount($amount);

        if (bccomp($formatted, '0.00', 2) !== 1) {
            throw CommitmentAllocationException::amountRequired();
        }

        if ($commitment->user_id !== $transaction->user_id) {
            throw CommitmentAllocationException::ownershipMismatch();
        }

        if (
            filled($commitment->loan_id)
            && filled($transaction->loan_id)
            && $commitment->loan_id !== $transaction->loan_id
        ) {
            throw CommitmentAllocationException::loanMismatch();
        }

        $commitmentRemaining = $commitment->remainingAmount();

        if (bccomp($formatted, $commitmentRemaining, 2) === 1) {
            throw CommitmentAllocationException::exceedsCommitmentRemaining();
        }

        $transactionUnallocated = $this->unallocatedAmount($transaction);

        if (bccomp($formatted, $transactionUnallocated, 2) === 1) {
            throw CommitmentAllocationException::exceedsTransactionUnallocated();
        }

        $existing = $commitment->transactions()
            ->where('transactions.id', $transaction->id)
            ->first();

        if ($existing !== null) {
            $newPivotAmount = bcadd(
                InstallmentDistributor::formatAmount($existing->pivot->amount),
                $formatted,
                2,
            );

            $commitment->transactions()->updateExistingPivot($transaction->id, [
                'amount' => $newPivotAmount,
            ]);

            return;
        }

        $commitment->transactions()->attach($transaction->id, [
            'amount' => $formatted,
        ]);
    }

    public function unallocatedAmount(Transaction $transaction): string
    {
        $allocated = $transaction->commitments()
            ->withTrashed()
            ->sum('commitment_transaction.amount');

        return bcsub(
            InstallmentDistributor::formatAmount($transaction->amount),
            InstallmentDistributor::formatAmount($allocated),
            2,
        );
    }

    /**
     * Explicit opt-in policy: waterfill earliest unsatisfied loan Commitments.
     *
     * Not used by LoanPaymentService::registerPayment — call this only when a
     * product flow deliberately chooses automatic distribution.
     *
     * @return Collection<int, array{commitment: Commitment, amount: string}>
     */
    public function allocateTowardLoan(Transaction $transaction, ?Commitment $preferred = null): Collection
    {
        if ($transaction->loan_id === null) {
            return collect();
        }

        return DB::transaction(function () use ($transaction, $preferred): Collection {
            $allocations = collect();
            $remaining = $this->unallocatedAmount($transaction);

            if (bccomp($remaining, '0.00', 2) !== 1) {
                return $allocations;
            }

            $commitments = Commitment::query()
                ->where('loan_id', $transaction->loan_id)
                ->whereNull('deleted_at')
                ->orderBy('sequence')
                ->orderBy('event_date')
                ->orderBy('id')
                ->get()
                ->filter(fn (Commitment $commitment): bool => ! $commitment->isSatisfied())
                ->values();

            if ($preferred !== null && ! $preferred->isSatisfied()) {
                $commitments = $commitments
                    ->reject(fn (Commitment $commitment): bool => $commitment->id === $preferred->id)
                    ->prepend($preferred)
                    ->values();
            }

            foreach ($commitments as $commitment) {
                if (bccomp($remaining, '0.00', 2) !== 1) {
                    break;
                }

                $apply = bccomp($remaining, $commitment->remainingAmount(), 2) === 1
                    ? $commitment->remainingAmount()
                    : $remaining;

                if (bccomp($apply, '0.00', 2) !== 1) {
                    continue;
                }

                $this->allocate($commitment, $transaction, $apply);
                $allocations->push([
                    'commitment' => $commitment->fresh(),
                    'amount' => $apply,
                ]);
                $remaining = bcsub($remaining, $apply, 2);
            }

            return $allocations;
        });
    }
}
