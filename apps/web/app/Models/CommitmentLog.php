<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'commitment_id',
    'channel',
    'status',
    'error',
])]
class CommitmentLog extends Model
{
    use HasUuids;

    public function commitment(): BelongsTo
    {
        return $this->belongsTo(Commitment::class);
    }
}
