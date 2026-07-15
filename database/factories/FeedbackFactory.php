<?php

namespace Database\Factories;

use App\Enums\FeedbackStatus;
use App\Enums\FeedbackType;
use App\Models\Feedback;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Feedback>
 */
class FeedbackFactory extends Factory
{
    protected $model = Feedback::class;

    public function definition(): array
    {
        return [
            'user_id' => null,
            'protocol' => sprintf('FDB-%s-%s', now()->format('Y'), strtoupper(Str::random(8))),
            'type' => fake()->randomElement(FeedbackType::cases()),
            'status' => FeedbackStatus::OPEN,
            'subject' => fake()->sentence(4),
            'message' => fake()->paragraph(),
            'attempted_action' => null,
            'context' => null,
            'attachment_path' => null,
        ];
    }
}
