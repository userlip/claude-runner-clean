<?php

namespace Database\Factories;

use App\Enums\ResearchModule;
use Illuminate\Database\Eloquent\Factories\Factory;

class ResearchReportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'module' => fake()->randomElement(ResearchModule::cases()),
            'title' => fake()->sentence(4),
            'summary' => fake()->paragraph(),
            'content' => fake()->paragraphs(3, true),
            'findings_count' => fake()->numberBetween(0, 50),
            'proposals_created' => fake()->numberBetween(0, 10),
            'file_path' => null,
            'task_id' => null,
        ];
    }
}
