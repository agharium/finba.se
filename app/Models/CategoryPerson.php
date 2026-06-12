<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Facades\Auth;

class CategoryPerson extends Pivot
{
    protected $table = 'category_person';

    public $incrementing = false;

    protected static function booted(): void
    {
        static::creating(function (CategoryPerson $pivot): void {
            $pivot->user_id ??= Auth::id();
        });
    }
}