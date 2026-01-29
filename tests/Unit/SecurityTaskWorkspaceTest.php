<?php

namespace Tests\Unit;

use App\Enums\SecurityRunStatus;
use App\Enums\TaskStatus;
use App\Models\AiProvider;
use App\Models\Repository;
use App\Models\SecurityRun;
use App\Models\Task;
use App\Services\SecurityManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityTaskWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_task_has_workspace_path_set(): void
    {
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
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('ensureTaskForRun');
        $method->setAccessible(true);

        $task = $method->invoke($service, $run, $repo);

        // Verify the task was created with a workspace_path
        $this->assertNotNull($task->workspace_path);
        $this->assertStringStartsWith('/home/ploi/workspaces/test-repo-', $task->workspace_path);
        $this->assertEquals(8, strlen(basename($task->workspace_path)) - strlen('test-repo-'));
    }

    public function test_major_upgrade_task_has_workspace_path_set(): void
    {
        $orchestrator = AiProvider::factory()->codex()->create(['name' => 'codex-test']);
        config(['services.security_ai.orchestrator_provider_id' => $orchestrator->id]);

        $repo = Repository::factory()->create([
            'name' => 'my-app',
            'full_name' => 'org/my-app',
        ]);

        $service = app(SecurityManagementService::class);

        // Use reflection to access the private createMajorUpgradeRun method
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('createMajorUpgradeRun');
        $method->setAccessible(true);

        $upgradeRun = $method->invoke($service, $repo, 42, []);

        // Get the task that was created
        $task = Task::find($upgradeRun->created_by_task_id);

        // Verify the task was created with a workspace_path
        $this->assertNotNull($task->workspace_path);
        $this->assertStringStartsWith('/home/ploi/workspaces/my-app-', $task->workspace_path);
    }

    public function test_workspace_path_follows_standard_format(): void
    {
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

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('ensureTaskForRun');
        $method->setAccessible(true);

        $task = $method->invoke($service, $run, $repo);

        // Verify workspace_path uses slugified repo name
        $this->assertMatchesRegularExpression(
            '#^/home/ploi/workspaces/my-complex-repo-name-[a-zA-Z0-9]{8}$#',
            $task->workspace_path
        );
    }

    public function test_task_working_directory_returns_workspace_path(): void
    {
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

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('ensureTaskForRun');
        $method->setAccessible(true);

        $task = $method->invoke($service, $run, $repo);

        // Verify getWorkingDirectoryAttribute returns the workspace_path
        $this->assertEquals($task->workspace_path, $task->working_directory);
    }

    public function test_existing_task_is_reused_for_security_run(): void
    {
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

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('ensureTaskForRun');
        $method->setAccessible(true);

        $task = $method->invoke($service, $run, $repo);

        // Verify the existing task is reused
        $this->assertEquals($existingTask->id, $task->id);
        $this->assertEquals('/home/ploi/workspaces/test-repo-existing', $task->workspace_path);
    }
}
