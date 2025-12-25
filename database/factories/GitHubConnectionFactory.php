<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GitHubConnection>
 */
class GitHubConnectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'access_token' => fake()->sha256(),
            'github_user_id' => (string) fake()->randomNumber(8),
            'github_username' => fake()->userName(),
            'scopes' => ['repo'],
        ];
    }
}
