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
            ['name' => 'kimi'],
            [
                'display_name' => 'Kimi',
                'base_url' => 'https://api.kimi.com/coding/',
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
