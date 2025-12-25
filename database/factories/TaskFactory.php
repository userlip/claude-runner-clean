<?php

namespace Database\Factories;

use App\Enums\TaskStatus;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'uuid' => Str::uuid(),
            'site_id' => Site::factory()->active(),
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
}
