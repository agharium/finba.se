<?php

namespace App\Services;

use App\Enums\LoanStatus;
use App\Enums\LoanType;
use App\Enums\TransactionType;
use App\Exceptions\LoanOriginationException;
use App\Models\Commitment;
use App\Models\Loan;
use App\Models\Transaction;
use App\Models\User;
use App\Support\InstallmentDistributor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

readonly class LoanOriginationResult
{
    /**
     * @param  Collection<int, Commitment>  $commitments
     */
    public function __construct(
        public Loan $loan,
        public Transaction $transaction,
        public Collection $commitments,
    ) {}
}

class LoanOriginationService
{
    public function __construct(
        private LoanCommitmentScheduleService $scheduleService,
    ) {}

    /**
     * Create a LENT or BORROWED loan with the initial money-moved Transaction.
     * Optional schedule generates Commitments (expectations), never Transactions.
     *
     * @param  array{
     *     type: LoanType|string,
     *     original_amount: float|string|int,
     *     person_id?: ?string,
     *     description?: ?string,
     *     date?: Carbon|string|null,
     *     category_id?: ?string,
     *     installments_count?: int|null,
     *     first_due_date?: Carbon|string|null,
     * }  $data
     */
    public function create(User $user, array $data): LoanOriginationResult
    {
        $type = $data['type'] ?? null;

        if ($type instanceof LoanType) {
            $type = $type->value;
        }

        if (! in_array($type, [LoanType::LENT->value, LoanType::BORROWED->value], true)) {
            throw LoanOriginationException::unsupportedType();
        }

        $amount = InstallmentDistributor::formatAmount($data['original_amount'] ?? 0);

        if (bccomp($amount, '0.00', 2) !== 1) {
            throw LoanOriginationException::amountRequired();
        }

        if (blank($data['person_id'] ?? null)) {
            throw LoanOriginationException::personRequired();
        }

        $installmentsCount = isset($data['installments_count']) ? (int) $data['installments_count'] : null;
        $firstDueDate = $data['first_due_date'] ?? null;

        if ($installmentsCount !== null) {
            if (
                $installmentsCount < 1
                || $installmentsCount > InstallmentDistributor::MAX_INSTALLMENTS
                || blank($firstDueDate)
            ) {
                throw LoanOriginationException::invalidSchedule();
            }
        }

        $loanType = LoanType::from($type);
        $transactionType = $loanType === LoanType::LENT
            ? TransactionType::EXPENSE
            : TransactionType::INCOME;
        $date = $data['date'] ?? now()->toDateString();

        return DB::transaction(function () use ($user, $data, $loanType, $amount, $transactionType, $date, $installmentsCount, $firstDueDate): LoanOriginationResult {
            $loan = Loan::query()->create([
                'user_id' => $user->id,
                'person_id' => $data['person_id'],
                'type' => $loanType,
                'status' => LoanStatus::OPEN,
                'original_amount' => $amount,
                'description' => $data['description'] ?? null,
            ]);

            $transaction = Transaction::query()->create([
                'user_id' => $user->id,
                'type' => $transactionType,
                'amount' => $amount,
                'description' => $data['description'] ?? $this->defaultTransactionDescription($loanType),
                'date' => $date,
                'status' => 'PAID',
                'person_id' => $data['person_id'],
                'category_id' => $data['category_id'] ?? null,
                'loan_id' => $loan->id,
            ]);

            $commitments = collect();

            if ($installmentsCount !== null) {
                $commitments = $this->scheduleService->generate(
                    $loan,
                    $installmentsCount,
                    $firstDueDate,
                    $amount,
                );
            }

            return new LoanOriginationResult($loan, $transaction, $commitments);
        });
    }

    private function defaultTransactionDescription(LoanType $type): string
    {
        return match ($type) {
            LoanType::LENT => 'Empréstimo concedido',
            LoanType::BORROWED => 'Empréstimo recebido',
            default => 'Empréstimo',
        };
    }
}
