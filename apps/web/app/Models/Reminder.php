<?php

namespace App\Models;

use App\Enums\ReminderType;
use App\Enums\ReminderRecurrence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'person_id',
    'loan_id',
    'recurring_transaction_id',
    'title',
    'description',
    'type',
    'event_date',
    'notification_offsets',
    'channels',
    'next_run_at',
    'last_sent_at',
    'recurrence',
    'is_active',
])]
class Reminder extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'notification_offsets' => 'array',
            'channels' => 'array',
            'event_date' => 'date',
            'next_run_at' => 'datetime',
            'last_sent_at' => 'datetime',
            'is_active' => 'boolean',
            'type' => ReminderType::class,
            'recurrence' => ReminderRecurrence::class,
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
        return $this->hasMany(ReminderLog::class);
    }
}