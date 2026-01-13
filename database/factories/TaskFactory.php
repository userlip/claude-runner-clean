<?php

namespace Database\Factories;

use App\Enums\TaskStatus;
use App\Models\AiProvider;
use App\Models\Message;
use App\Models\Repository;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TaskFactory extends Factory
{
    public function definition(): array
    {
        $repository = Repository::factory()->create();

        return [
            'uuid' => Str::uuid(),
            'user_id' => $repository->user_id,
            'repository_id' => $repository->id,
            'site_id' => null,
            'ai_provider_id' => null,
            'workspace_path' => '/home/ploi/workspaces/'.fake()->slug(1).'-'.Str::random(8),
            'session_id' => Str::uuid(),
            'status' => TaskStatus::Pending,
            'max_turns' => null,
            'started_at' => null,
            'completed_at' => null,
        ];
    }

    public function generalChat(?User $user = null): static
    {
        return $this->state(function () use ($user) {
            $user ??= User::factory()->create();

            return [
                'user_id' => $user->id,
                'repository_id' => null,
                'site_id' => null,
                'workspace_path' => null,
            ];
        });
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

    public function withProvider(AiProvider $provider): static
    {
        return $this->state(['ai_provider_id' => $provider->id]);
    }

    public function ralph(): static
    {
        return $this->state(fn (array $attributes) => [
            'ralph_enabled' => true,
            'ralph_iteration' => 1,
            'ralph_max_iterations' => 25,
            'ralph_rotation_threshold' => 0.7,
            'ralph_branch_name' => 'ralph/test-feature',
        ])->has(Message::factory()->count(1));
    }
}
