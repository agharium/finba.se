<?php

namespace App\Models;

use App\Enums\CommitmentRecurrence;
use App\Enums\CommitmentType;
use App\Support\InstallmentDistributor;
use Database\Factories\CommitmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'user_id',
    'person_id',
    'loan_id',
    'recurring_transaction_id',
    'title',
    'description',
    'amount',
    'sequence',
    'type',
    'event_date',
    'notification_offsets',
    'channels',
    'next_run_at',
    'last_sent_at',
    'recurrence',
    'is_active',
])]
class Commitment extends Model
{
    /** @use HasFactory<CommitmentFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'sequence' => 'integer',
            'notification_offsets' => 'array',
            'channels' => 'array',
            'event_date' => 'date',
            'next_run_at' => 'datetime',
            'last_sent_at' => 'datetime',
            'is_active' => 'boolean',
            'type' => CommitmentType::class,
            'recurrence' => CommitmentRecurrence::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function recurringTransaction(): BelongsTo
    {
        return $this->belongsTo(RecurringTransaction::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(CommitmentLog::class);
    }

    public function transactions(): BelongsToMany
    {
        return $this->belongsToMany(Transaction::class, 'commitment_transaction')
            ->withPivot('amount');
    }

    public function allocatedAmount(): string
    {
        $allocated = $this->transactions()->sum('commitment_transaction.amount');

        return InstallmentDistributor::formatAmount($allocated);
    }

    public function remainingAmount(): string
    {
        $expected = InstallmentDistributor::formatAmount($this->amount ?? 0);

        return bcsub($expected, $this->allocatedAmount(), 2);
    }

    public function isSatisfied(): bool
    {
        if ($this->amount === null) {
            return false;
        }

        return bccomp($this->allocatedAmount(), InstallmentDistributor::formatAmount($this->amount), 2) >= 0;
    }

    public function isPartiallyPaid(): bool
    {
        $allocated = $this->allocatedAmount();

        return bccomp($allocated, '0.00', 2) === 1 && ! $this->isSatisfied();
    }

    public function fulfillmentLabel(): string
    {
        if ($this->isSatisfied()) {
            return 'Quitado';
        }

        if ($this->isPartiallyPaid()) {
            return 'Parcial';
        }

        return 'Em aberto';
    }

    /**
     * Display label like "3/6" derived from the current loan schedule — not durable identity.
     */
    public function scheduleLabel(): ?string
    {
        if ($this->loan_id === null || $this->sequence === null) {
            return null;
        }

        $total = static::query()
            ->where('loan_id', $this->loan_id)
            ->whereNull('deleted_at')
            ->count();

        if ($total < 1) {
            return null;
        }

        return $this->sequence.'/'.$total;
    }

    /**
     * @param  Builder<Commitment>  $query
     * @return Builder<Commitment>
     */
    public function scopeUnsatisfied(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->whereDoesntHave('transactions')
                ->orWhereRaw(
                    '(SELECT COALESCE(SUM(commitment_transaction.amount), 0)
                      FROM commitment_transaction
                      WHERE commitment_transaction.commitment_id = commitments.id) < commitments.amount'
                );
        });
    }
}
