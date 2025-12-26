<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AiProvider>
 */
class AiProviderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'claude',
            'display_name' => 'Claude',
            'base_url' => null,
            'api_key' => null,
            'model' => null,
            'is_active' => true,
            'is_default' => true,
            'quota_limit' => 10000000,
            'quota_period' => 'monthly',
            'quota_used' => 0,
            'quota_resets_at' => now()->startOfMonth()->addMonth(),
        ];
    }

    public function claude(): static
    {
        return $this->state([
            'name' => 'claude',
            'display_name' => 'Claude',
            'base_url' => null,
            'api_key' => null,
            'model' => null,
            'is_default' => true,
            'quota_period' => 'monthly',
        ]);
    }

    public function glm(): static
    {
        return $this->state([
            'name' => 'glm',
            'display_name' => 'GLM (z.ai)',
            'base_url' => 'https://api.z.ai/api/anthropic',
            'api_key' => 'test-api-key',
            'model' => 'GLM-4.6',
            'is_default' => false,
            'quota_period' => '5-hour',
            'quota_limit' => 50000000,
            'quota_resets_at' => now()->addHours(5),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
