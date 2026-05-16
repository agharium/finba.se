<?php

namespace Database\Factories;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        return [
            'amount' => fake()->randomFloat(2, 10, 5_000),
            'type' => fake()->randomElement(['INCOME', 'EXPENSE']),
            'description' => fake()->optional()->sentence(),
            'user_id' => User::factory(),
            'category_id' => null,
            'person_id' => null,
            'loan_id' => null,
            'installment' => null,
        ];
    }
}
