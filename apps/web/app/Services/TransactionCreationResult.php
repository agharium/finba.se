<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\Transaction;

readonly class TransactionCreationResult
{
    public function __construct(
        public Transaction|Loan $record,
        public bool $isReceivableSale,
    ) {}

    public static function receivable(Loan $loan): self
    {
        return new self(record: $loan, isReceivableSale: true);
    }

    public static function transaction(Transaction $transaction): self
    {
        return new self(record: $transaction, isReceivableSale: false);
    }
}
