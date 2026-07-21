<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'reminder_id',
    'channel',
    'status',
    'error',
])]
class ReminderLog extends Model
{
    use HasUuids;

    public function reminder(): BelongsTo
    {
        return $this->belongsTo(Reminder::class);
    }
}
