<?php

namespace App\Observers;

use App\Models\Transaction;
use App\Services\CityUsageService;

class TransactionObserver
{
    public function __construct(
        private CityUsageService $cityUsageService,
    ) {}

    public function created(Transaction $transaction): void
    {
        if ($transaction->city_id) {
            $this->cityUsageService->record($transaction->city);
        }
    }

    public function updated(Transaction $transaction): void
    {
        if (! $transaction->wasChanged('city_id')) {
            return;
        }

        $this->cityUsageService->recordIfChanged(
            $transaction->city,
            $transaction->getOriginal('city_id'),
        );
    }
}
