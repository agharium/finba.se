<?php

namespace App\Models;

use App\Enums\InstallmentGroupStatus;
use Database\Factories\InstallmentGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'user_id',
    'category_id',
    'person_id',
    'total_amount',
    'installments_count',
    'description',
    'first_date',
    'status',
])]
class InstallmentGroup extends Model
{
    /** @use HasFactory<InstallmentGroupFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'installments_count' => 'integer',
            'first_date' => 'date',
            'status' => InstallmentGroupStatus::class,
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

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'installment_group_id');
    }
}
