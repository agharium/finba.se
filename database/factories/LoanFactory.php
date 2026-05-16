<?php

namespace Database\Factories;

use App\Models\Loan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Loan>
 */
class LoanFactory extends Factory
{
    protected $model = Loan::class;

    public function definition(): array
    {
        return [
            'name' => fake()->sentence(2),
            'total_amount' => fake()->randomFloat(2, 100, 50_000),
            'day_of_month' => fake()->numberBetween(1, 28),
            'user_id' => User::factory(),
        ];
    }
}
