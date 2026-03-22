<?php

namespace Database\Factories;

use App\Enums\ProposalType;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlaybookFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'proposal_type' => fake()->randomElement(ProposalType::cases()),
            'project' => fake()->optional()->slug(2),
            'description' => fake()->sentence(),
            'prompt_template' => fake()->paragraphs(2, true),
            'skills' => null,
            'is_active' => true,
            'times_used' => 0,
            'success_count' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
