<?php

namespace App\Jobs;

use App\Enums\SiteStatus;
use App\Models\Site;
use App\Models\Task;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class DeployToSiteJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(
        public Task $task,
        public string $subdomain,
        public string $phpVersion = '8.4',
        public string $webDirectory = '/public',
        public ?string $databaseName = null,
    ) {}

    public function handle(): void
    {
        $workspacePath = $this->task->workspace_path;
        $branch = $this->subdomain;
        $domain = "{$this->subdomain}.marin.sh";
        $serverId = config('services.ploi.server_id');

        Log::info("Deploying to site: {$domain}", [
            'task_id' => $this->task->id,
            'branch' => $branch,
        ]);

        try {
            // Step 1: Commit any pending changes
            $this->runGitCommand(['git', 'add', '-A'], $workspacePath);
            $this->runGitCommand([
                'git', 'commit', '-m', 'Deploy to site', '--allow-empty',
            ], $workspacePath);

            // Step 2: Create and push branch
            $this->runGitCommand(['git', 'checkout', '-b', $branch], $workspacePath);
            $this->runGitCommand(['git', 'push', '-u', 'origin', $branch], $workspacePath);

            // Step 3: Create Ploi site
            $this->runPloiCommand([
                'site:create',
                '--server='.$serverId,
                '--domain='.$domain,
                '--web-directory='.$this->webDirectory,
                '--project-type=laravel',
                '--no-interaction',
            ]);

            // Step 4: Install repository
            $this->runPloiCommand([
                'repository:install',
                '--server='.$serverId,
                '--site='.$domain,
                '--no-interaction',
            ]);

            // Step 5: Create database if specified
            if ($this->databaseName) {
                $this->runPloiCommand([
                    'database:create',
                    '--server='.$serverId,
                    '--name='.$this->databaseName,
                    '--no-interaction',
                ]);
            }

            // Step 6: Deploy
            $this->runPloiCommand([
                'deploy',
                '--server='.$serverId,
                '--site='.$domain,
                '--no-interaction',
            ]);

            // Step 7: Create Site record
            $site = Site::create([
                'repository_id' => $this->task->repository_id,
                'domain' => $domain,
                'branch' => $branch,
                'path' => "/home/ploi/{$domain}",
                'php_version' => $this->phpVersion,
                'web_directory' => $this->webDirectory,
                'database_name' => $this->databaseName,
                'status' => SiteStatus::Active,
            ]);

            // Update task to point to site
            $this->task->update(['site_id' => $site->id]);

            Log::info("Site deployed successfully: {$domain}", [
                'site_id' => $site->id,
            ]);

        } catch (\Exception $e) {
            Log::error("Deploy failed: {$e->getMessage()}", [
                'task_id' => $this->task->id,
            ]);
            throw $e;
        }
    }

    protected function runGitCommand(array $command, string $cwd): void
    {
        $result = Process::path($cwd)->run($command);

        if (! $result->successful()) {
            throw new \RuntimeException(
                'Git command failed: '.implode(' ', $command)."\n".$result->errorOutput()
            );
        }
    }

    protected function runPloiCommand(array $arguments): void
    {
        $command = array_merge(['ploi'], $arguments);
        $result = Process::timeout(300)->run($command);

        if (! $result->successful()) {
            Log::warning('Ploi command output: '.$result->output());
            // Don't fail on Ploi errors, some commands may not be critical
        }
    }
}
