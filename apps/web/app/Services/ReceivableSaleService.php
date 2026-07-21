<?php

namespace App\Services;

use App\Enums\IncomePaymentMode;
use App\Enums\LoanStatus;
use App\Enums\LoanType;
use App\Enums\TransactionType;
use App\Exceptions\ReceivableSaleException;
use App\Models\Loan;
use App\Models\User;

class ReceivableSaleService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, array $data): Loan
    {
        if (! $user->usesAccountsReceivable()) {
            throw ReceivableSaleException::featureUnavailable();
        }

        if (($data['type'] ?? null) !== TransactionType::INCOME->value) {
            throw ReceivableSaleException::featureUnavailable();
        }

        if (($data['payment_mode'] ?? IncomePaymentMode::NOW->value) !== IncomePaymentMode::LATER->value) {
            throw ReceivableSaleException::featureUnavailable();
        }

        if (blank($data['person_id'] ?? null)) {
            throw ReceivableSaleException::personRequired();
        }

        $amount = (float) ($data['amount'] ?? 0);

        if ($amount <= 0) {
            throw ReceivableSaleException::amountRequired();
        }

        return Loan::query()->create([
            'user_id' => $user->id,
            'person_id' => $data['person_id'],
            'type' => LoanType::RECEIVABLE,
            'status' => LoanStatus::OPEN,
            'original_amount' => $amount,
            'description' => filled($data['description'] ?? null)
                ? $data['description']
                : 'Venda a prazo',
        ]);
    }
}
