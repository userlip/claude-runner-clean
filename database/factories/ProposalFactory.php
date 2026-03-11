<?php

namespace Database\Factories;

use App\Enums\ProposalPriority;
use App\Enums\ProposalStatus;
use App\Enums\ProposalType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProposalFactory extends Factory
{
    public function definition(): array
    {
        return [
            'uuid' => Str::uuid(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'priority' => ProposalPriority::Medium,
            'status' => ProposalStatus::Pending,
            'project' => fake()->slug(2),
            'type' => ProposalType::Other,
        ];
    }

    public function approved(): static
    {
        return $this->state([
            'status' => ProposalStatus::Approved,
            'approved_at' => now(),
            'decision_time_seconds' => fake()->numberBetween(60, 3600),
        ]);
    }

    public function rejected(): static
    {
        return $this->state([
            'status' => ProposalStatus::Rejected,
            'rejected_at' => now(),
            'rejected_reason' => fake()->sentence(),
            'decision_time_seconds' => fake()->numberBetween(60, 3600),
        ]);
    }

    public function highPriority(): static
    {
        return $this->state([
            'priority' => ProposalPriority::High,
        ]);
    }

    public function executed(bool $success = true): static
    {
        return $this->approved()->state([
            'execution_completed_at' => now(),
            'execution_success' => $success,
        ]);
    }
}
