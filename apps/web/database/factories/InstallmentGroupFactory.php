<?php

namespace Database\Factories;

use App\Enums\InstallmentGroupStatus;
use App\Models\InstallmentGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InstallmentGroup>
 */
class InstallmentGroupFactory extends Factory
{
    protected $model = InstallmentGroup::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'category_id' => null,
            'person_id' => null,
            'total_amount' => fake()->randomFloat(2, 100, 10_000),
            'installments_count' => fake()->numberBetween(2, 24),
            'description' => fake()->optional()->sentence(),
            'first_date' => fake()->date(),
            'status' => fake()->randomElement(InstallmentGroupStatus::cases())->value,
        ];
    }
}
