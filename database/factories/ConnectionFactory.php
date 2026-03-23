<?php

namespace Database\Factories;

use App\Enums\ConnectionType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Connection>
 */
class ConnectionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => ConnectionType::Asana,
            'name' => fake()->company(),
            'credentials' => fake()->sha256(),
            'metadata' => null,
            'is_active' => true,
        ];
    }

    public function asana(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ConnectionType::Asana,
            'name' => 'Asana',
            'credentials' => fake()->sha256(),
            'metadata' => [
                'default_workspace_id' => (string) fake()->randomNumber(8),
            ],
        ]);
    }

    public function github(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ConnectionType::GitHub,
            'name' => 'GitHub',
            'credentials' => fake()->sha256(),
            'metadata' => [
                'github_user_id' => (string) fake()->randomNumber(8),
                'github_username' => fake()->userName(),
                'scopes' => ['repo'],
            ],
        ]);
    }

    public function googleAnalytics(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ConnectionType::GoogleAnalytics,
            'credentials' => json_encode(['type' => 'service_account', 'client_email' => fake()->email()]),
            'metadata' => [
                'property_id' => 'properties/'.fake()->randomNumber(9),
            ],
        ]);
    }

    public function searchConsole(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ConnectionType::SearchConsole,
            'credentials' => json_encode(['type' => 'service_account', 'client_email' => fake()->email()]),
        ]);
    }
}
