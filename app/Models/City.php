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
    'name',
    'region_code',
    'country_code',
    'usage_count',
    'last_used_at',
])]
class City extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected static function booted(): void
    {
        static::saving(function (City $city): void {
            $city->name = \App\Support\LocationNameNormalizer::normalize($city->name) ?? $city->name;
        });
    }

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'usage_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function people(): BelongsToMany
    {
        return $this->belongsToMany(Person::class, 'person_city')
            ->using(PersonCity::class)
            ->withPivot('user_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}