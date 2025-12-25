<?php

use App\Jobs\CloneRepositoryJob;
use App\Models\Repository;
use App\Models\Task;
use Illuminate\Support\Facades\Process;

test('job clones repository to workspace path', function () {
    Process::fake();

    $repository = Repository::factory()->create([
        'clone_url' => 'https://github.com/test/repo.git',
    ]);
    $task = Task::factory()->create([
        'repository_id' => $repository->id,
        'workspace_path' => '/home/ploi/workspaces/repo-abc123',
    ]);

    CloneRepositoryJob::dispatchSync($task);

    Process::assertRan(function ($process) {
        return str_contains(implode(' ', $process->command), 'git clone');
    });
});

test('job builds correct clone command', function () {
    Process::fake();

    $repository = Repository::factory()->create([
        'clone_url' => 'https://github.com/test/my-repo.git',
        'default_branch' => 'main',
    ]);
    $task = Task::factory()->create([
        'repository_id' => $repository->id,
        'workspace_path' => '/home/ploi/workspaces/my-repo-xyz',
    ]);

    CloneRepositoryJob::dispatchSync($task);

    Process::assertRan(function ($process) {
        $cmd = implode(' ', $process->command);

        return str_contains($cmd, 'https://github.com/test/my-repo.git')
            && str_contains($cmd, '/home/ploi/workspaces/my-repo-xyz');
    });
});
