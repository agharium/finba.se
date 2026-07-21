<?php

namespace App\Models;

use Database\Factories\UserProviderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'provider', 'provider_id'])]
class UserProvider extends Model
{
    use HasUuids;

    protected $table = 'user_providers';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
