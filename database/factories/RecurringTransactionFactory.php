<?php

namespace Database\Factories;

use App\Enums\AmountMode;
use App\Enums\RecurringFrequency;
use App\Models\RecurringTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecurringTransaction>
 */
class RecurringTransactionFactory extends Factory
{
    protected $model = RecurringTransaction::class;

    public function definition(): array
    {
        $amountMode = fake()->randomElement(AmountMode::cases());

        return [
            'user_id' => User::factory(),
            'name' => fake()->words(2, true),
            'type' => fake()->randomElement(['INCOME', 'EXPENSE']),
            'amount_mode' => $amountMode->value,
            'amount' => $amountMode === AmountMode::FIXED
                ? fake()->randomFloat(2, 20, 5_000)
                : null,
            'category_id' => null,
            'person_id' => null,
            'frequency' => fake()->randomElement(RecurringFrequency::cases())->value,
            'next_occurrence_at' => fake()->dateTimeBetween('now', '+1 year')->format('Y-m-d'),
            'is_active' => true,
        ];
    }
}
