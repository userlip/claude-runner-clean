<?php

namespace Database\Factories;

use App\Enums\PersonaStatus;
use App\Models\AiProvider;
use App\Models\Repository;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PersonaFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->randomElement([
            'SEO Analyzer',
            'Security Auditor',
            'Performance Monitor',
            'Code Quality Inspector',
            'Dependency Checker',
            'Content Strategist',
            'Accessibility Reviewer',
            'API Health Monitor',
        ]);

        return [
            'user_id' => User::factory(),
            'repository_id' => Repository::factory(),
            'ai_provider_id' => null,
            'name' => $name,
            'slug' => fake()->unique()->slug(2),
            'description' => fake()->sentence(),
            'master_prompt' => fake()->paragraphs(3, true),
            'mcp_guidance' => fake()->optional()->paragraph(),
            'status' => PersonaStatus::Active,
            'is_active' => true,
            'last_run_at' => null,
            'total_runs' => 0,
            'total_proposals' => 0,
        ];
    }

    public function paused(): static
    {
        return $this->state([
            'status' => PersonaStatus::Paused,
            'is_active' => false,
        ]);
    }

    public function running(): static
    {
        return $this->state([
            'status' => PersonaStatus::Running,
        ]);
    }

    public function withRuns(int $runs = 5, int $proposals = 3): static
    {
        return $this->state([
            'total_runs' => $runs,
            'total_proposals' => $proposals,
            'last_run_at' => fake()->dateTimeBetween('-1 week'),
        ]);
    }

    public function withAiProvider(): static
    {
        return $this->state([
            'ai_provider_id' => AiProvider::factory(),
        ]);
    }
}
