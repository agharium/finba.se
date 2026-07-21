<?php

namespace App\Services;

use App\Models\InstallmentGroup;
use App\Models\Transaction;
use Illuminate\Support\Collection;

readonly class InstallmentCreationResult
{
    /**
     * @param  Collection<int, Transaction>  $transactions
     */
    public function __construct(
        public InstallmentGroup $group,
        public Collection $transactions,
    ) {}
}
