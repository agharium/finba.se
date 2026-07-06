<?php

namespace App\Services;

use App\Enums\IncomePaymentMode;
use App\Enums\LoanStatus;
use App\Enums\LoanType;
use App\Enums\TransactionType;
use App\Exceptions\ReceivablePaymentException;
use App\Models\Loan;
use App\Models\Transaction;
use App\Models\User;

class ReceivablePaymentService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function supports(User $user, array $data): bool
    {
        if (! $user->usesAccountsReceivable()) {
            return false;
        }

        if (($data['type'] ?? null) !== TransactionType::INCOME->value) {
            return false;
        }

        if (($data['payment_mode'] ?? IncomePaymentMode::NOW->value) === IncomePaymentMode::LATER->value) {
            return false;
        }

        if (blank($data['loan_id'] ?? null)) {
            return false;
        }

        $loan = Loan::query()
            ->where('user_id', $user->id)
            ->find($data['loan_id']);

        return $loan?->type === LoanType::RECEIVABLE;
    }

    /**
     * @param  array<string, mixed>  $formData
     */
    public function registerPayment(User $user, Loan $loan, array $formData): Transaction
    {
        $payload = [
            'type' => TransactionType::INCOME->value,
            'payment_mode' => IncomePaymentMode::NOW->value,
            'amount' => $formData['amount'],
            'description' => $formData['description'] ?? null,
            'category_id' => $formData['category_id'] ?? null,
            'loan_id' => $loan->id,
            'status' => 'PAID',
            'date' => $formData['date'],
            'user_id' => $user->id,
        ];

        return $this->create(
            $user,
            $payload,
            app(TransactionService::class)->prepareAttributes($payload),
        );
    }

    public function paidAmountFor(Loan $loan): string
    {
        return $this->paidAmount($loan);
    }

    public function remainingBalanceFor(Loan $loan): string
    {
        return $this->remainingBalance($loan);
    }

    public function formatMoney(float|string|null $amount): string
    {
        return 'R$ ' . number_format((float) $amount, 2, ',', '.');
    }

    /**
     * @return array{
     *     original: string,
     *     received: string,
     *     remaining: string,
     * }
     */
    public function summaryFor(Loan $loan): array
    {
        $paid = $this->paidAmountFor($loan);

        return [
            'original' => $this->formatMoney($loan->original_amount),
            'received' => $this->formatMoney($paid),
            'remaining' => $this->formatMoney($this->remainingBalanceFor($loan)),
        ];
    }

    /**
     * @param  array<string, mixed>  $data  Normalized form data.
     * @param  array<string, mixed>  $attributes  Persistable transaction attributes.
     */
    public function create(User $user, array $data, array $attributes): Transaction
    {
        $loan = $this->resolveReceivableLoan($user, $data);

        $amount = (float) ($data['amount'] ?? 0);

        if ($amount <= 0) {
            throw ReceivablePaymentException::amountRequired();
        }

        $this->assertPaymentWithinBalance($loan, $amount);

        $transaction = Transaction::query()->create($attributes);

        $this->closeReceivableIfFullyPaid($loan);

        return $transaction;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveReceivableLoan(User $user, array $data): Loan
    {
        $loan = Loan::query()
            ->where('user_id', $user->id)
            ->find($data['loan_id'] ?? null);

        if ($loan === null) {
            throw ReceivablePaymentException::loanNotFound();
        }

        if ($loan->type !== LoanType::RECEIVABLE) {
            throw ReceivablePaymentException::invalidLoanType();
        }

        if ($loan->status !== LoanStatus::OPEN) {
            throw ReceivablePaymentException::loanNotOpen();
        }

        return $loan;
    }

    private function assertPaymentWithinBalance(Loan $loan, float $amount): void
    {
        $remaining = $this->remainingBalance($loan);

        if (bccomp($this->formatAmount($amount), $remaining, 2) === 1) {
            throw ReceivablePaymentException::overpayment();
        }
    }

    private function closeReceivableIfFullyPaid(Loan $loan): void
    {
        $loan->refresh();

        if (bccomp($this->paidAmount($loan), $this->formatAmount($loan->original_amount), 2) >= 0) {
            $loan->update(['status' => LoanStatus::CLOSED]);
        }
    }

    private function paidAmount(Loan $loan): string
    {
        $paid = $loan->transactions()
            ->where('type', TransactionType::INCOME)
            ->where('status', 'PAID')
            ->sum('amount');

        return $this->formatAmount($paid);
    }

    private function remainingBalance(Loan $loan): string
    {
        return bcsub(
            $this->formatAmount($loan->original_amount),
            $this->paidAmount($loan),
            2,
        );
    }

    private function formatAmount(float|string|null $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
