<?php

namespace Database\Factories;

use App\Enums\CommitmentChannel;
use App\Enums\CommitmentType;
use App\Models\Commitment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Commitment>
 */
class CommitmentFactory extends Factory
{
    protected $model = Commitment::class;

    public function definition(): array
    {
        $eventDate = fake()->dateTimeBetween('now', '+6 months');

        return [
            'user_id' => User::factory(),
            'person_id' => null,
            'loan_id' => null,
            'recurring_transaction_id' => null,
            'title' => fake()->sentence(3),
            'description' => null,
            'amount' => fake()->randomFloat(2, 10, 5_000),
            'sequence' => null,
            'type' => CommitmentType::CUSTOM->value,
            'event_date' => $eventDate,
            'notification_offsets' => [
                ['value' => 0, 'unit' => 'DAY'],
            ],
            'channels' => [CommitmentChannel::EMAIL->value],
            'next_run_at' => $eventDate,
            'last_sent_at' => null,
            'recurrence' => null,
            'is_active' => true,
        ];
    }
}
