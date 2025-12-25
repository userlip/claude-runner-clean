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

    public int $timeout = 300;

    public int $tries = 1;

    public function __construct(public Task $task) {}

    public function handle(): void
    {
        $repository = $this->task->repository;
        $workspacePath = $this->task->workspace_path;

        Log::info("Cloning repository {$repository->full_name}", [
            'task_id' => $this->task->id,
            'workspace' => $workspacePath,
        ]);

        // Ensure parent directory exists
        $parentDir = dirname($workspacePath);
        if (! is_dir($parentDir)) {
            mkdir($parentDir, 0755, true);
        }

        // Clone the repository
        $result = Process::timeout(300)->run([
            'git', 'clone',
            '--branch', $repository->default_branch ?? 'main',
            '--single-branch',
            $repository->clone_url,
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
    }
}
