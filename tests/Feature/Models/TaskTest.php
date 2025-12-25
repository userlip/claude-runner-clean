<?php

use App\Enums\TaskStatus;
use App\Models\Repository;
use App\Models\Site;
use App\Models\Task;

test('task belongs to site', function () {
    $task = Task::factory()->onSite()->create();

    expect($task->site)->toBeInstanceOf(Site::class);
});

test('site has many tasks', function () {
    $site = Site::factory()->active()->create();
    Task::factory()->count(3)->create([
        'repository_id' => $site->repository_id,
        'site_id' => $site->id,
        'workspace_path' => null,
    ]);

    expect($site->tasks)->toHaveCount(3);
});

test('task auto-generates uuid and session_id on create', function () {
    $repository = Repository::factory()->create();
    $task = Task::create([
        'repository_id' => $repository->id,
        'workspace_path' => '/home/ploi/workspaces/test',
    ]);

    expect($task->uuid)->not->toBeNull();
    expect($task->session_id)->not->toBeNull();
});

test('task uses uuid as route key', function () {
    $task = Task::factory()->create();

    expect($task->getRouteKeyName())->toBe('uuid');
});

test('can mark task as running', function () {
    $task = Task::factory()->create();

    $task->markAsRunning();

    expect($task->status)->toBe(TaskStatus::Running);
    expect($task->started_at)->not->toBeNull();
});

test('can mark task as completed', function () {
    $task = Task::factory()->running()->create();

    $task->markAsCompleted();

    expect($task->status)->toBe(TaskStatus::Completed);
    expect($task->completed_at)->not->toBeNull();
});

test('task belongs to repository', function () {
    $repository = Repository::factory()->create();
    $task = Task::factory()->create(['repository_id' => $repository->id, 'site_id' => null]);

    expect($task->repository->id)->toBe($repository->id);
});

test('task can have workspace_path', function () {
    $task = Task::factory()->create([
        'workspace_path' => '/home/ploi/workspaces/my-repo-abc123',
        'site_id' => null,
    ]);

    expect($task->workspace_path)->toBe('/home/ploi/workspaces/my-repo-abc123');
});

test('task can work on site instead of workspace', function () {
    $site = Site::factory()->active()->create();
    $task = Task::factory()->create([
        'repository_id' => $site->repository_id,
        'site_id' => $site->id,
        'workspace_path' => null,
    ]);

    expect($task->site->id)->toBe($site->id);
    expect($task->workspace_path)->toBeNull();
});

test('task working_directory returns workspace_path when set', function () {
    $task = Task::factory()->create([
        'workspace_path' => '/home/ploi/workspaces/test',
        'site_id' => null,
    ]);

    expect($task->working_directory)->toBe('/home/ploi/workspaces/test');
});

test('task working_directory returns site path when on site', function () {
    $site = Site::factory()->active()->create(['path' => '/home/ploi/my-site.marin.sh']);
    $task = Task::factory()->create([
        'repository_id' => $site->repository_id,
        'site_id' => $site->id,
        'workspace_path' => null,
    ]);

    expect($task->working_directory)->toBe('/home/ploi/my-site.marin.sh');
});
