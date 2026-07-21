<?php

namespace Database\Factories;

use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        return [
            'name' => fake()->word(),
            'types' => fake()->randomElement([
                [TransactionType::INCOME->value],
                [TransactionType::EXPENSE->value],
                [TransactionType::INCOME->value, TransactionType::EXPENSE->value],
            ]),
            'user_id' => User::factory(),
            'parent_id' => null,
        ];
    }
}
