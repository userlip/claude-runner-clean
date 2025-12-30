<?php

namespace App\Jobs;

use App\Models\Task;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class CloneRepositoryJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(public Task $task) {}

    public function handle(): void
    {
        $repository = $this->task->repository;
        $workspacePath = $this->task->workspace_path;

        $this->task->setInitStatus('cloning');

        Log::info("Cloning repository {$repository->full_name}", [
            'task_id' => $this->task->id,
            'workspace' => $workspacePath,
        ]);

        // Ensure parent directory exists
        $parentDir = dirname($workspacePath);
        if (! is_dir($parentDir)) {
            mkdir($parentDir, 0755, true);
        }

        // Clone the repository (use authenticated URL for private repos)
        $cloneUrl = $repository->getAuthenticatedCloneUrl();

        $result = Process::timeout(300)->run([
            'git', 'clone',
            '--branch', $repository->default_branch ?? 'main',
            '--single-branch',
            $cloneUrl,
            $workspacePath,
        ]);

        if (! $result->successful()) {
            Log::error('Failed to clone repository', [
                'task_id' => $this->task->id,
                'error' => $result->errorOutput(),
            ]);
            throw new \RuntimeException('Failed to clone repository: '.$result->errorOutput());
        }

        Log::info('Repository cloned successfully', [
            'task_id' => $this->task->id,
            'workspace' => $workspacePath,
        ]);

        // Auto-copy default .env if one exists
        $defaultEnvConfig = $repository->defaultEnvConfig();
        if ($defaultEnvConfig) {
            $envPath = $workspacePath.'/.env';
            file_put_contents($envPath, $defaultEnvConfig->content);

            Log::info('Default .env config copied to workspace', [
                'task_id' => $this->task->id,
                'env_config' => $defaultEnvConfig->name,
                'env_path' => $envPath,
            ]);
        }

        // Run composer install if composer.json exists
        $this->runDependencyInstallation($workspacePath);

        // Mark initialization as complete
        $this->task->setInitStatus('completed');
    }

    protected function runDependencyInstallation(string $workspacePath): void
    {
        // Environment variables required for composer and npm to work properly
        $env = [
            'HOME' => '/home/ploi',
            'COMPOSER_HOME' => '/home/ploi/.config/composer',
            'PATH' => '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin',
        ];

        // Run composer install if composer.json exists
        if (file_exists($workspacePath.'/composer.json')) {
            $this->task->setInitStatus('composer_install');
            Log::info('Running composer install', ['task_id' => $this->task->id]);

            $result = Process::timeout(300)
                ->path($workspacePath)
                ->env($env)
                ->run(['composer', 'install', '--no-interaction', '--no-progress']);

            if ($result->successful()) {
                Log::info('Composer install completed', ['task_id' => $this->task->id]);
                $this->task->update(['ran_composer_install' => true]);
            } else {
                Log::warning('Composer install failed', [
                    'task_id' => $this->task->id,
                    'error' => $result->errorOutput(),
                ]);
            }
        }

        // Run npm install and build if package.json exists
        if (file_exists($workspacePath.'/package.json')) {
            $this->task->setInitStatus('npm_install');
            Log::info('Running npm install', ['task_id' => $this->task->id]);

            $result = Process::timeout(300)
                ->path($workspacePath)
                ->env($env)
                ->run(['npm', 'install']);

            if ($result->successful()) {
                Log::info('npm install completed', ['task_id' => $this->task->id]);
                $this->task->update(['ran_npm_install' => true]);

                // Run npm run build
                $this->task->setInitStatus('npm_build');
                Log::info('Running npm run build', ['task_id' => $this->task->id]);

                $buildResult = Process::timeout(300)
                    ->path($workspacePath)
                    ->env($env)
                    ->run(['npm', 'run', 'build']);

                if ($buildResult->successful()) {
                    Log::info('npm run build completed', ['task_id' => $this->task->id]);
                    $this->task->update(['ran_npm_build' => true]);
                } else {
                    Log::warning('npm run build failed', [
                        'task_id' => $this->task->id,
                        'error' => $buildResult->errorOutput(),
                    ]);
                }
            } else {
                Log::warning('npm install failed', [
                    'task_id' => $this->task->id,
                    'error' => $result->errorOutput(),
                ]);
            }
        }
    }
}
