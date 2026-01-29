<?php

use App\Enums\SecurityRunStatus;
use App\Enums\TaskStatus;
use App\Models\AiProvider;
use App\Models\Repository;
use App\Models\SecurityRun;
use App\Models\Task;
use App\Services\SecurityManagementService;

test('security task has workspace path set', function () {
    $repo = Repository::factory()->create([
        'name' => 'test-repo',
        'full_name' => 'org/test-repo',
    ]);

    $run = SecurityRun::create([
        'repository_id' => $repo->id,
        'github_pr_id' => 123,
        'github_pr_number' => 10,
        'pr_title' => 'Bump dependency',
        'status' => SecurityRunStatus::Pending,
    ]);

    $service = app(SecurityManagementService::class);

    // Use reflection to access the private ensureTaskForRun method
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('ensureTaskForRun');
    $method->setAccessible(true);

    $task = $method->invoke($service, $run, $repo);

    // Verify the task was created with a workspace_path
    expect($task->workspace_path)->not->toBeNull();
    expect($task->workspace_path)->toStartWith('/home/ploi/workspaces/test-repo-');
    expect(strlen(basename($task->workspace_path)) - strlen('test-repo-'))->toBe(8);
});

test('major upgrade task has workspace path set', function () {
    $orchestrator = AiProvider::factory()->codex()->create(['name' => 'codex-test']);
    config(['services.security_ai.orchestrator_provider_id' => $orchestrator->id]);

    $repo = Repository::factory()->create([
        'name' => 'my-app',
        'full_name' => 'org/my-app',
    ]);

    $service = app(SecurityManagementService::class);

    // Use reflection to access the private createMajorUpgradeRun method
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('createMajorUpgradeRun');
    $method->setAccessible(true);

    $upgradeRun = $method->invoke($service, $repo, 42, []);

    // Get the task that was created
    $task = Task::find($upgradeRun->created_by_task_id);

    // Verify the task was created with a workspace_path
    expect($task->workspace_path)->not->toBeNull();
    expect($task->workspace_path)->toStartWith('/home/ploi/workspaces/my-app-');
});

test('workspace path follows standard format', function () {
    $repo = Repository::factory()->create([
        'name' => 'My Complex Repo Name',
        'full_name' => 'org/my-complex-repo-name',
    ]);

    $run = SecurityRun::create([
        'repository_id' => $repo->id,
        'github_pr_id' => 456,
        'github_pr_number' => 20,
        'pr_title' => 'Security update',
        'status' => SecurityRunStatus::Pending,
    ]);

    $service = app(SecurityManagementService::class);

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('ensureTaskForRun');
    $method->setAccessible(true);

    $task = $method->invoke($service, $run, $repo);

    // Verify workspace_path uses slugified repo name
    expect($task->workspace_path)->toMatch('#^/home/ploi/workspaces/my-complex-repo-name-[a-zA-Z0-9]{8}$#');
});

test('task working directory returns workspace path', function () {
    $repo = Repository::factory()->create([
        'name' => 'test-repo',
        'full_name' => 'org/test-repo',
    ]);

    $run = SecurityRun::create([
        'repository_id' => $repo->id,
        'github_pr_id' => 789,
        'github_pr_number' => 30,
        'pr_title' => 'Bump packages',
        'status' => SecurityRunStatus::Pending,
    ]);

    $service = app(SecurityManagementService::class);

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('ensureTaskForRun');
    $method->setAccessible(true);

    $task = $method->invoke($service, $run, $repo);

    // Verify getWorkingDirectoryAttribute returns the workspace_path
    expect($task->working_directory)->toBe($task->workspace_path);
});

test('existing task is reused for security run', function () {
    $repo = Repository::factory()->create([
        'name' => 'test-repo',
        'full_name' => 'org/test-repo',
    ]);

    // Create an existing task
    $existingTask = Task::create([
        'title' => 'Security PR #10: Existing',
        'repository_id' => $repo->id,
        'workspace_path' => '/home/ploi/workspaces/test-repo-existing',
        'status' => TaskStatus::Pending,
    ]);

    $run = SecurityRun::create([
        'repository_id' => $repo->id,
        'github_pr_id' => 111,
        'github_pr_number' => 10,
        'pr_title' => 'Existing task',
        'status' => SecurityRunStatus::Pending,
        'task_id' => $existingTask->id,
    ]);

    $service = app(SecurityManagementService::class);

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('ensureTaskForRun');
    $method->setAccessible(true);

    $task = $method->invoke($service, $run, $repo);

    // Verify the existing task is reused
    expect($task->id)->toBe($existingTask->id);
    expect($task->workspace_path)->toBe('/home/ploi/workspaces/test-repo-existing');
});
