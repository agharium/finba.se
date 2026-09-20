<?php

namespace App\Services;

use App\Enums\LoanStatus;
use App\Enums\LoanType;
use App\Exceptions\LoanPaymentException;
use App\Models\Commitment;
use App\Models\Loan;
use App\Models\Transaction;
use App\Models\User;
use App\Support\InstallmentDistributor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

readonly class LoanPaymentResult
{
    /**
     * @param  Collection<int, array{commitment: Commitment, amount: string}>  $allocations
     */
    public function __construct(
        public Transaction $transaction,
        public Collection $allocations,
        public string $unallocatedAmount,
        public Loan $loan,
    ) {}
}

class LoanPaymentService
{
    public function __construct(
        private LoanBalanceService $balanceService,
        private CommitmentAllocationService $allocationService,
        private TransactionService $transactionService,
    ) {}

    /**
     * Record an actual Loan repayment Transaction.
     *
     * Does not waterfill or recalculate Commitments.
     * If commitment_id is provided, allocates only to that Commitment up to its remaining amount;
     * any excess stays unallocated on the Transaction.
     *
     * @param  array{
     *     amount: float|string|int,
     *     date: string,
     *     description?: ?string,
     *     category_id?: ?string,
     *     commitment_id?: ?string,
     *     allow_overpayment?: bool,
     * }  $formData
     */
    public function registerPayment(User $user, Loan $loan, array $formData): LoanPaymentResult
    {
        if ($loan->user_id !== $user->id) {
            throw LoanPaymentException::loanNotFound();
        }

        if (! in_array($loan->type, [LoanType::LENT, LoanType::BORROWED], true)) {
            throw LoanPaymentException::unsupportedLoanType();
        }

        if ($loan->status !== LoanStatus::OPEN) {
            throw LoanPaymentException::loanNotOpen();
        }

        $amount = InstallmentDistributor::formatAmount($formData['amount'] ?? 0);

        if (bccomp($amount, '0.00', 2) !== 1) {
            throw LoanPaymentException::amountRequired();
        }

        $allowOverpayment = (bool) ($formData['allow_overpayment'] ?? false);
        $remaining = $this->balanceService->remainingBalance($loan);

        if (! $allowOverpayment && bccomp($amount, $remaining, 2) === 1) {
            throw LoanPaymentException::overpayment();
        }

        $explicitCommitment = null;

        if (filled($formData['commitment_id'] ?? null)) {
            $explicitCommitment = Commitment::query()
                ->where('user_id', $user->id)
                ->where('loan_id', $loan->id)
                ->find($formData['commitment_id']);

            if ($explicitCommitment === null) {
                throw LoanPaymentException::commitmentNotFound();
            }
        }

        return DB::transaction(function () use ($user, $loan, $formData, $amount, $explicitCommitment): LoanPaymentResult {
            $payload = [
                'type' => $this->balanceService->repaymentTransactionType($loan)->value,
                'amount' => $amount,
                'description' => $formData['description'] ?? null,
                'category_id' => $formData['category_id'] ?? null,
                'person_id' => $loan->person_id,
                'loan_id' => $loan->id,
                'status' => 'PAID',
                'date' => $formData['date'],
                'user_id' => $user->id,
            ];

            $transaction = Transaction::query()->create(
                $this->transactionService->prepareAttributes($payload),
            );

            $allocations = collect();

            if ($explicitCommitment !== null) {
                $apply = bccomp($amount, $explicitCommitment->remainingAmount(), 2) === 1
                    ? $explicitCommitment->remainingAmount()
                    : $amount;

                if (bccomp($apply, '0.00', 2) === 1) {
                    $this->allocationService->allocate($explicitCommitment, $transaction, $apply);
                    $allocations->push([
                        'commitment' => $explicitCommitment->fresh(),
                        'amount' => $apply,
                    ]);
                }
            }

            $this->balanceService->closeIfFullyPaid($loan);

            $transaction = $transaction->fresh();

            return new LoanPaymentResult(
                transaction: $transaction,
                allocations: $allocations,
                unallocatedAmount: $this->allocationService->unallocatedAmount($transaction),
                loan: $loan->fresh(),
            );
        });
    }
}
