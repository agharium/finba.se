<?php

namespace App\Services;

use App\Models\InstallmentGroup;
use Illuminate\Support\Collection;

readonly class InstallmentCreationResult
{
    /**
     * @param  Collection<int, \App\Models\Transaction>  $transactions
     */
    public function __construct(
        public InstallmentGroup $group,
        public Collection $transactions,
    ) {}
}
