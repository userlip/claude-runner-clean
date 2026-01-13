<?php

namespace Tests\Unit\Services;

use App\DataObjects\RalphState;
use App\Models\Task;
use App\Services\RalphWorkspaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class RalphWorkspaceServiceTest extends TestCase
{
    use RefreshDatabase;

    private RalphWorkspaceService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Use real filesystem but clean up after test
        $this->service = app(RalphWorkspaceService::class);
    }

    protected function tearDown(): void
    {
        // Clean up any test directories
        if (File::exists('/tmp/test-workspace')) {
            File::deleteDirectory('/tmp/test-workspace');
        }

        parent::tearDown();
    }

    public function test_initializes_ralph_workspace(): void
    {
        $task = Task::factory()->create([
            'workspace_path' => '/tmp/test-workspace',
        ]);

        $config = [
            'branch_name' => 'ralph/test-feature',
            'verification_command' => 'php artisan test',
            'stories' => [
                [
                    'id' => 'US-001',
                    'title' => 'Test story',
                    'priority' => 1,
                    'passes' => false,
                ],
            ],
        ];

        $this->service->initialize($task, $config);

        $ralphPath = $task->getRalphWorkspacePath();

        $this->assertFileExists($ralphPath.'/prompt.md');
        $this->assertFileExists($ralphPath.'/prd.json');
        $this->assertFileExists($ralphPath.'/progress.txt');
        $this->assertFileExists($ralphPath.'/guardrails.md');
        $this->assertFileExists($ralphPath.'/activity.log');
    }

    public function test_reads_ralph_state(): void
    {
        $task = Task::factory()->create([
            'workspace_path' => '/tmp/test-workspace',
        ]);

        $this->service->initialize($task, [
            'branch_name' => 'ralph/test',
            'stories' => [['id' => 'US-001', 'priority' => 1, 'passes' => false]],
        ]);

        $state = $this->service->readState($task);

        $this->assertInstanceOf(RalphState::class, $state);
        $this->assertIsArray($state->prd);
        $this->assertArrayHasKey('userStories', $state->prd);
    }

    public function test_updates_prd_story_as_passed(): void
    {
        $task = Task::factory()->create(['workspace_path' => '/tmp/test-workspace']);

        $this->service->initialize($task, [
            'branch_name' => 'ralph/test',
            'stories' => [['id' => 'US-001', 'priority' => 1, 'passes' => false]],
        ]);

        $state = $this->service->readState($task);

        // Create updated PRD array (since prd is readonly)
        $updatedPrd = $state->prd;
        $updatedPrd['userStories'][0]['passes'] = true;

        $this->service->updatePrd($task, $updatedPrd);

        $updatedState = $this->service->readState($task);
        $this->assertTrue($updatedState->prd['userStories'][0]['passes']);
    }

    public function test_appends_progress_learnings(): void
    {
        $task = Task::factory()->create(['workspace_path' => '/tmp/test-workspace']);

        $this->service->initialize($task, ['branch_name' => 'ralph/test', 'stories' => []]);

        $learning = "## US-001\n- New learning";
        $this->service->appendProgress($task, $learning);

        $state = $this->service->readState($task);
        $this->assertStringContainsString($learning, $state->progress);
    }

    public function test_appends_guardrail(): void
    {
        $task = Task::factory()->create(['workspace_path' => '/tmp/test-workspace']);

        $this->service->initialize($task, ['branch_name' => 'ralph/test', 'stories' => []]);

        $guardrail = "### sign: test guardrail\n- trigger: something\n- instruction: do this";
        $this->service->appendGuardrail($task, $guardrail);

        $state = $this->service->readState($task);
        $this->assertStringContainsString($guardrail, $state->guardrails);
    }

    public function test_logs_activity(): void
    {
        $task = Task::factory()->create(['workspace_path' => '/tmp/test-workspace']);

        $this->service->initialize($task, ['branch_name' => 'ralph/test', 'stories' => []]);

        $activity = [
            'iteration' => 1,
            'timestamp' => now()->toIso8601String(),
            'story' => 'US-001',
            'status' => 'passed',
        ];

        $this->service->logActivity($task, $activity);

        $logPath = $task->getRalphWorkspacePath().'/activity.log';
        $logContent = File::get($logPath);

        $this->assertStringContainsString('"iteration":1', $logContent);
        $this->assertStringContainsString('"story":"US-001"', $logContent);
    }
}
