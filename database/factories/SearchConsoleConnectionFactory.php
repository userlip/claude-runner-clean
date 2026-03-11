<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SearchConsoleConnection>
 */
class SearchConsoleConnectionFactory extends Factory
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
            'name' => fake()->company(),
            'credentials_json' => json_encode([
                'type' => 'service_account',
                'project_id' => 'test-project-'.fake()->randomNumber(5),
                'private_key_id' => fake()->sha256(),
                'private_key' => "-----BEGIN RSA PRIVATE KEY-----\nMIItest\n-----END RSA PRIVATE KEY-----\n",
                'client_email' => fake()->unique()->safeEmail(),
                'client_id' => (string) fake()->randomNumber(8),
                'auth_uri' => 'https://accounts.google.com/o/oauth2/auth',
                'token_uri' => 'https://oauth2.googleapis.com/token',
            ]),
        ];
    }
}
