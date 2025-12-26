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
            ['name' => 'glm'],
            [
                'display_name' => 'GLM (z.ai)',
                'base_url' => 'https://api.z.ai/api/anthropic',
                'model' => 'GLM-4.6',
                'is_active' => false,
                'is_default' => false,
                'quota_limit' => 50000000,
                'quota_period' => '5-hour',
                'quota_resets_at' => now()->addHours(5),
            ]
        );
    }
}
