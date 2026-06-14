<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Facades\Auth;

class PersonCity extends Pivot
{
    protected $table = 'person_city';

    public $incrementing = false;

    protected static function booted(): void
    {
        static::creating(function (PersonCity $pivot): void {
            $pivot->user_id ??= Auth::id();
        });
    }
}