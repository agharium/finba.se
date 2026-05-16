<?php

namespace App\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'types', 'user_id', 'parent_id'])]
class Category extends Model
{
    use HasUuids;

    protected $table = 'categories';

    protected function casts(): array
    {
        return [
            'types' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function descendantsIds(): array
    {
        $ids = [];

        $this->loadMissing('children');

        foreach ($this->children as $child) {
            $ids[] = $child->id;

            $ids = array_merge($ids, $child->descendantsIds());
        }

        return $ids;
    }
}
