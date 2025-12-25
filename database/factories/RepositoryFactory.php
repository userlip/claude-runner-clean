<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RepositoryFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->slug(2);
        $owner = fake()->userName();

        return [
            'user_id' => User::factory(),
            'github_id' => fake()->unique()->randomNumber(8),
            'name' => $name,
            'full_name' => "{$owner}/{$name}",
            'clone_url' => "https://github.com/{$owner}/{$name}.git",
            'ssh_url' => "git@github.com:{$owner}/{$name}.git",
            'default_branch' => 'main',
            'private' => fake()->boolean(30),
            'description' => fake()->optional()->sentence(),
        ];
    }

    public function private(): static
    {
        return $this->state(['private' => true]);
    }

    public function public(): static
    {
        return $this->state(['private' => false]);
    }
}
