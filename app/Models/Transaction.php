<?php

namespace App\Models;

use App\Enums\Purpose;
use App\Enums\TransactionType;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['status',
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
])]
class Transaction extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'date' => 'date',
            'purpose' => Purpose::class,
            'type' => TransactionType::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'person_id');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class, 'loan_id');
    }

    public function installmentGroup(): BelongsTo
    {
        return $this->belongsTo(InstallmentGroup::class, 'installment_group_id');
    }

    public function recurringTransaction(): BelongsTo
    {
        return $this->belongsTo(RecurringTransaction::class, 'recurring_transaction_id');
    }

    public function titheCalculation(): BelongsTo
    {
        return $this->belongsTo(TitheCalculation::class, 'tithe_calculation_id');
    }

    public function titheCalculations(): BelongsToMany
    {
        return $this->belongsToMany(
            TitheCalculation::class,
            'tithe_calculation_transaction',
            'transaction_id',
            'tithe_calculation_id',
        );
    }
}
