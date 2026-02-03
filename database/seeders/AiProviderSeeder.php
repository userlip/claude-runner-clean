<?php

namespace Database\Seeders;

use App\Models\AiProvider;
use Illuminate\Database\Seeder;

class AiProviderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        AiProvider::firstOrCreate(
            ['name' => 'claude'],
            [
                'display_name' => 'Claude',
                'is_active' => true,
                'is_default' => true,
                'quota_limit' => 10000000,
                'quota_period' => 'monthly',
                'quota_resets_at' => now()->startOfMonth()->addMonth(),
            ]
        );

        AiProvider::firstOrCreate(
            ['name' => 'codex'],
            [
                'display_name' => 'Codex',
                'is_active' => true,
                'is_default' => false,
                'quota_limit' => 10000000,
                'quota_period' => 'monthly',
                'quota_resets_at' => now()->startOfMonth()->addMonth(),
            ]
        );

        AiProvider::firstOrCreate(
            ['name' => 'glm'],
            [
                'display_name' => 'GLM (z.ai)',
                'base_url' => 'https://api.z.ai/api/anthropic',
                'model' => 'GLM-4.7',
                'is_active' => false,
                'is_default' => false,
                'quota_limit' => 50000000,
                'quota_period' => '5-hour',
                'quota_resets_at' => now()->addHours(5),
            ]
        );

        AiProvider::firstOrCreate(
            ['name' => 'minimax'],
            [
                'display_name' => 'Minimax',
                'base_url' => 'https://api.minimax.io/anthropic',
                'model' => 'MiniMax-M2.1',
                'is_active' => false,
                'is_default' => false,
                'quota_limit' => 50000000,
                'quota_period' => 'monthly',
                'quota_resets_at' => now()->startOfMonth()->addMonth(),
            ]
        );

        AiProvider::firstOrCreate(
            ['name' => 'kimi'],
            [
                'display_name' => 'Kimi',
                'base_url' => 'https://api.kimi.com/coding/',
                'api_key' => 'env('KIMI_API_KEY')',
                'model' => 'kimi-k2.5',
                'context_window' => 262144,
                'is_active' => true,
                'is_default' => false,
                'quota_limit' => 50000000,
                'quota_period' => 'monthly',
                'quota_resets_at' => now()->startOfMonth()->addMonth(),
            ]
        );
    }
}
