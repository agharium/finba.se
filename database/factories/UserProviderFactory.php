<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserProvider>
 */
class UserProviderFactory extends Factory
{
    protected $model = UserProvider::class;

    public function definition(): array
    {
        $provider = fake()->randomElement(['github', 'google', 'other']);

        return [
            'user_id' => User::factory(),
            'provider' => $provider,
            'provider_id' => fake()->unique()->uuid(),
            'access_token' => null,
            'refresh_token' => null,
        ];
    }
}
