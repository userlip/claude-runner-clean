<?php

namespace Database\Factories;

use App\Enums\TaskStatus;
use App\Models\Repository;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'uuid' => Str::uuid(),
            'repository_id' => Repository::factory(),
            'site_id' => null,
            'workspace_path' => '/home/ploi/workspaces/'.fake()->slug(1).'-'.Str::random(8),
            'session_id' => Str::uuid(),
            'status' => TaskStatus::Pending,
            'max_turns' => null,
            'started_at' => null,
            'completed_at' => null,
        ];
    }

    public function running(): static
    {
        return $this->state([
            'status' => TaskStatus::Running,
            'started_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state([
            'status' => TaskStatus::Completed,
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state([
            'status' => TaskStatus::Failed,
            'started_at' => now()->subMinutes(2),
            'completed_at' => now(),
        ]);
    }

    public function withMaxTurns(int $turns): static
    {
        return $this->state(['max_turns' => $turns]);
    }

    public function onSite(?Site $site = null): static
    {
        return $this->state(function () use ($site) {
            $site ??= Site::factory()->active()->create();

            return [
                'repository_id' => $site->repository_id,
                'site_id' => $site->id,
                'workspace_path' => null,
            ];
        });
    }
}
