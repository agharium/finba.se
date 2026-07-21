<?php

namespace App\Services;

use App\Models\TitheCalculation;
use App\Models\Transaction;
use Illuminate\Support\Collection;

readonly class TitheDeliveryResult
{
    /**
     * @param  Collection<int, Transaction>  $deliveryTransactions
     */
    public function __construct(
        public TitheCalculation $calculation,
        public Collection $deliveryTransactions,
    ) {}
}
