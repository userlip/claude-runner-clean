<?php

namespace Database\Factories;

use App\Enums\QuotaPeriod;
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
            'quota_period' => QuotaPeriod::Monthly,
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
            'quota_period' => QuotaPeriod::Monthly,
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
            'quota_period' => QuotaPeriod::FiveHour,
            'quota_limit' => 50000000,
            'quota_resets_at' => now()->addHours(5),
        ]);
    }

    public function minimax(): static
    {
        return $this->state([
            'name' => 'minimax',
            'display_name' => 'Minimax',
            'base_url' => 'https://api.minimax.io/anthropic',
            'api_key' => 'test-api-key',
            'model' => 'MiniMax-M2.1',
            'is_default' => false,
            'quota_period' => QuotaPeriod::Monthly,
            'quota_limit' => 50000000,
            'quota_resets_at' => now()->startOfMonth()->addMonth(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
