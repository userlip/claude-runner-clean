<?php

namespace Database\Factories;

use App\Enums\SecurityRunStatus;
use App\Models\Repository;
use Illuminate\Database\Eloquent\Factories\Factory;

class SecurityRunFactory extends Factory
{
    public function definition(): array
    {
        $fromVersion = fake()->numerify('#.#.#');
        $toVersion = fake()->numerify('#.#.#');

        return [
            'repository_id' => Repository::factory(),
            'github_pr_id' => fake()->unique()->numberBetween(1000, 999999),
            'github_pr_number' => fake()->numberBetween(1, 9999),
            'pr_title' => "Bump package from {$fromVersion} to {$toVersion}",
            'from_version' => $fromVersion,
            'to_version' => $toVersion,
            'risk_level' => fake()->optional()->randomElement(['low', 'medium', 'high']),
            'status' => fake()->randomElement(SecurityRunStatus::cases()),
            'decision_summary' => fake()->optional()->sentence(),
            'merge_commit_sha' => null,
            'last_checked_at' => fake()->optional()->dateTime(),
            'error_message' => null,
            'task_id' => null,
        ];
    }

    public function merged(): static
    {
        return $this->state([
            'status' => SecurityRunStatus::Merged,
            'merge_commit_sha' => fake()->sha1(),
        ]);
    }
}
