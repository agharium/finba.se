<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'types', 'user_id', 'geo_city_id'])]
class Person extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'people';

    protected function casts(): array
    {
        return [
            'types' => 'array',
            'geo_city_id' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'person_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_person')
            ->using(CategoryPerson::class)
            ->withPivot('user_id');
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class, 'person_id');
    }
}
