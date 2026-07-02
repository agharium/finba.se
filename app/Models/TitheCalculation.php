<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'user_id',
    'period_start',
    'period_end',
    'base_amount',
    'tithe_amount',
    'offering_target_amount',
    'offering_paid_amount',
    'firstfruits_amount',
])]
class TitheCalculation extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'base_amount' => 'decimal:2',
            'tithe_amount' => 'decimal:2',
            'offering_target_amount' => 'decimal:2',
            'offering_paid_amount' => 'decimal:2',
            'firstfruits_amount' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function incomeTransactions(): BelongsToMany
    {
        return $this->belongsToMany(
            Transaction::class,
            'tithe_calculation_transaction',
            'tithe_calculation_id',
            'transaction_id',
        );
    }

    public function deliveryTransactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'tithe_calculation_id');
    }
}
