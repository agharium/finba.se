<?php

namespace App\Services;

use App\Enums\LoanStatus;
use App\Enums\LoanType;
use App\Enums\TransactionType;
use App\Models\Loan;
use App\Support\InstallmentDistributor;

/**
 * Loan remaining balance is derived from actual Transactions only.
 * Commitments never reduce the balance by merely existing.
 */
class LoanBalanceService
{
    public function repaymentTransactionType(Loan $loan): TransactionType
    {
        return match ($loan->type) {
            LoanType::LENT, LoanType::RECEIVABLE => TransactionType::INCOME,
            LoanType::BORROWED => TransactionType::EXPENSE,
        };
    }

    public function originationTransactionType(Loan $loan): ?TransactionType
    {
        return match ($loan->type) {
            LoanType::LENT => TransactionType::EXPENSE,
            LoanType::BORROWED => TransactionType::INCOME,
            LoanType::RECEIVABLE => null,
        };
    }

    public function repaidAmount(Loan $loan): string
    {
        $repaid = $loan->transactions()
            ->where('type', $this->repaymentTransactionType($loan))
            ->where('status', 'PAID')
            ->sum('amount');

        return InstallmentDistributor::formatAmount($repaid);
    }

    public function remainingBalance(Loan $loan): string
    {
        return bcsub(
            InstallmentDistributor::formatAmount($loan->original_amount),
            $this->repaidAmount($loan),
            2,
        );
    }

    public function closeIfFullyPaid(Loan $loan): void
    {
        $loan->refresh();

        if (bccomp($this->remainingBalance($loan), '0.00', 2) <= 0) {
            $loan->update(['status' => LoanStatus::CLOSED]);
        }
    }
}
