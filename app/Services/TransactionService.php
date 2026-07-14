<?php

namespace App\Services;

use App\Enums\IncomePaymentMode;
use App\Enums\TransactionEntryMode;
use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Arr;

class TransactionService
{
    public function __construct(
        private ReceivableSaleService $receivableSaleService,
        private ReceivablePaymentService $receivablePaymentService,
        private InstallmentGroupService $installmentGroupService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, array $data): TransactionCreationResult
    {
        $data['user_id'] = $user->id;
        $data['status'] ??= 'PAID';

        if ($this->isReceivableSale($user, $data)) {
            $loan = $this->receivableSaleService->create($user, $data);

            return TransactionCreationResult::receivable($loan);
        }

        if ($this->receivablePaymentService->supports($user, $data)) {
            $transaction = $this->receivablePaymentService->create(
                $user,
                $data,
                $this->prepareAttributes($data),
            );

            return TransactionCreationResult::transaction($transaction);
        }

        if ($this->installmentGroupService->supports($data)) {
            $result = $this->installmentGroupService->create($user, $data);

            return TransactionCreationResult::transaction($result->transactions->first());
        }

        $transaction = Transaction::query()->create($this->prepareAttributes($data));

        return TransactionCreationResult::transaction($transaction);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function isReceivableSale(User $user, array $data): bool
    {
        if (! $user->usesAccountsReceivable()) {
            return false;
        }

        return ($data['type'] ?? null) === TransactionType::INCOME->value
            && ($data['payment_mode'] ?? IncomePaymentMode::NOW->value) === IncomePaymentMode::LATER->value;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function normalizeFormData(array $data): array
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

        unset(
            $data['parent_category_id'],
            $data['child_category_id'],
            $data['payment_mode'],
            $data['entry_mode'],
            $data['installments_count'],
            $data['first_installment_date'],
            $data['has_loan'],
            $data['loan_link_kind'],
            $data['contribution_toggle'],
        );

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function prepareAttributes(array $data): array
    {
        $data = $this->normalizeFormData($data);

        return Arr::only($data, [
            'status',
            'type',
            'purpose',
            'amount',
            'description',
            'date',
            'user_id',
            'category_id',
            'person_id',
            'city_id',
            'loan_id',
            'installment_group_id',
            'installment_number',
            'recurring_transaction_id',
            'tithe_calculation_id',
        ]);
    }
}
