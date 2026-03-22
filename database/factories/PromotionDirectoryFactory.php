<?php

namespace Database\Factories;

use App\Enums\DirectoryCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PromotionDirectoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'uuid' => Str::uuid(),
            'name' => fake()->company(),
            'url' => fake()->url(),
            'category' => DirectoryCategory::Developer,
            'submission_type' => fake()->randomElement(['free', 'paid', 'invite_only']),
            'submission_url' => fake()->optional()->url(),
            'requirements' => null,
            'suitable_products' => null,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
