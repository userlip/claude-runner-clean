<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ScrappApi>
 */
class ScrappApiFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);
        $slug = Str::slug($name);

        return [
            'name' => ucwords($name),
            'slug' => $slug,
            'route_prefix' => 'api/'.$slug,
            'rapidapi_slug' => $slug.'-scraper',
            'is_active' => true,
            'last_tested_at' => null,
            'last_test_result' => null,
            'notes' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function passed(): static
    {
        return $this->state([
            'last_tested_at' => now(),
            'last_test_result' => 'passed',
        ]);
    }

    public function failed(): static
    {
        return $this->state([
            'last_tested_at' => now(),
            'last_test_result' => 'failed',
        ]);
    }

    public function running(): static
    {
        return $this->state([
            'last_tested_at' => now(),
            'last_test_result' => 'running',
        ]);
    }

    public function withNotes(string $notes): static
    {
        return $this->state(['notes' => $notes]);
    }
}
