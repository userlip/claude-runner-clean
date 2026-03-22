<?php

namespace Database\Factories;

use App\Enums\MajorUpgradeStatus;
use App\Models\Repository;
use Illuminate\Database\Eloquent\Factories\Factory;

class MajorUpgradeRunFactory extends Factory
{
    public function definition(): array
    {
        return [
            'repository_id' => Repository::factory(),
            'github_pr_number' => fake()->unique()->numberBetween(1, 9999),
            'status' => fake()->randomElement(MajorUpgradeStatus::cases()),
            'source_pr_url' => fake()->optional()->url(),
            'source_pr_sha' => fake()->optional()->sha1(),
            'work_branch' => fake()->optional()->slug(3),
            'upgrade_summary' => fake()->optional()->paragraph(),
            'error_message' => null,
            'review_site_url' => fake()->optional()->url(),
            'review_site_id' => null,
            'created_by_task_id' => null,
            'last_checked_at' => fake()->optional()->dateTime(),
        ];
    }

    public function completed(): static
    {
        return $this->state(['status' => MajorUpgradeStatus::Completed]);
    }

    public function failed(): static
    {
        return $this->state([
            'status' => MajorUpgradeStatus::Failed,
            'error_message' => fake()->sentence(),
        ]);
    }
}
