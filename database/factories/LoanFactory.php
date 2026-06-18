<?php

namespace Database\Factories;

use App\Enums\LoanStatus;
use App\Enums\LoanType;
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
            'description' => fake()->sentence(3),
            'total_amount' => fake()->randomFloat(2, 100, 50_000),
            'status' => fake()->randomElement(LoanStatus::cases())->value,
            'type' => fake()->randomElement(LoanType::cases())->value,
            'user_id' => User::factory(),
        ];
    }
}
