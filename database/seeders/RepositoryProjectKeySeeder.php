<?php

namespace Database\Seeders;

use App\Models\Repository;
use Illuminate\Database\Seeder;

class RepositoryProjectKeySeeder extends Seeder
{
    public function run(): void
    {
        $mappings = [
            'scrappa' => ['scrappa', 'scrappa-api', 'scrappa.io'],
            'rezensionsheld' => ['rezensionsheld', 'review-hero'],
            'claude_runner' => ['claude-runner', 'claude-runner.marin.sh'],
        ];

        foreach ($mappings as $projectKey => $possibleNames) {
            foreach ($possibleNames as $name) {
                Repository::where('name', 'LIKE', "%{$name}%")
                    ->whereNull('project_key')
                    ->update(['project_key' => $projectKey]);
            }
        }

        $this->command->info('Project keys assigned to repositories.');
    }
}
