<?php

namespace App\Console\Commands;

use App\Jobs\CloneRepositoryJob;
use App\Jobs\RunApiHealthCheckJob;
use App\Models\AiProvider;
use App\Models\Repository;
use App\Models\ScrappApi;
use App\Models\Task;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;

class RunApiHealthCheckCommand extends Command
{
    protected $signature = 'scrappa:health-check {--api= : Specific API ID to test}';

    protected $description = 'Run random API health check with Kimi';

    public function handle(): int
    {
        $api = $this->option('api')
            ? ScrappApi::findOrFail($this->option('api'))
            : ScrappApi::where('is_active', true)->inRandomOrder()->first();

        if (! $api) {
            $this->error('No active APIs found');

            return self::FAILURE;
        }

        $skill = collect(['scrappa-endpoint-testing', 'rapidapi-publishing'])->random();

        $repository = Repository::where('name', 'scrappa')->first();

        $kimiProvider = AiProvider::where('name', 'kimi')->first();

        if (! $repository || ! $kimiProvider) {
            $this->error('Scrappa repository or Kimi provider not found');

            return self::FAILURE;
        }

        $workspacePath = '/home/ploi/workspaces/scrappa-'.Str::random(8);

        $task = Task::create([
            'user_id' => 1,
            'repository_id' => $repository->id,
            'ai_provider_id' => $kimiProvider->id,
            'scrapp_api_id' => $api->id,
            'title' => "Auto: {$skill} for {$api->name}",
            'workspace_path' => $workspacePath,
        ]);

        Bus::chain([
            new CloneRepositoryJob($task),
            new RunApiHealthCheckJob($task, $api, $skill),
        ])->dispatch();

        $this->info("Started health check for {$api->name} with {$skill}");

        return self::SUCCESS;
    }
}
