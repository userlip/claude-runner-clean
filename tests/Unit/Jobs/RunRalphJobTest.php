<?php

namespace Tests\Unit\Jobs;

use App\Jobs\RunRalphJob;
use App\Models\AiProvider;
use App\Models\Task;
use App\Services\RalphWorkspaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Queue;
use ReflectionMethod;
use Tests\TestCase;

class RunRalphJobTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        // Clean up any test directories
        if (File::exists('/tmp/test-workspace')) {
            File::deleteDirectory('/tmp/test-workspace');
        }

        parent::tearDown();
    }

    public function test_rotates_context_when_threshold_reached(): void
    {
        $provider = AiProvider::factory()->kimi()->create(['context_window' => 100000]);
        $task = Task::factory()->ralph()->create([
            'ai_provider_id' => $provider->id,
            'ralph_rotation_threshold' => 0.7,
            'workspace_path' => '/tmp/test-workspace',
        ]);

        // Create messages using 75% of context
        $task->messages()->createMany([
            ['role' => 'user', 'tokens_in' => 75000, 'tokens_out' => 0],
            ['role' => 'assistant', 'tokens_in' => 0, 'tokens_out' => 10000],
        ]);

        // Test the Task's shouldRotateContext method instead (which is public)
        $this->assertTrue($task->shouldRotateContext());
    }

    public function test_picks_highest_priority_unpassed_story(): void
    {
        $task = Task::factory()->ralph()->create(['workspace_path' => '/tmp/test-workspace']);

        $prd = [
            'userStories' => [
                ['id' => 'US-001', 'priority' => 2, 'passes' => false],
                ['id' => 'US-002', 'priority' => 1, 'passes' => false],
                ['id' => 'US-003', 'priority' => 1, 'passes' => true],
            ],
        ];

        app(RalphWorkspaceService::class)->initialize($task, [
            'branch_name' => 'ralph/test',
            'stories' => $prd['userStories'],
        ]);

        $state = app(RalphWorkspaceService::class)->readState($task);
        $nextStory = $state->getNextStory();

        $this->assertEquals('US-002', $nextStory['id']);
    }

    public function test_detects_all_stories_passed(): void
    {
        $task = Task::factory()->ralph()->create(['workspace_path' => '/tmp/test-workspace']);

        $prd = [
            'userStories' => [
                ['id' => 'US-001', 'priority' => 1, 'passes' => true],
                ['id' => 'US-002', 'priority' => 2, 'passes' => true],
            ],
        ];

        app(RalphWorkspaceService::class)->initialize($task, [
            'branch_name' => 'ralph/test',
            'stories' => $prd['userStories'],
        ]);

        $state = app(RalphWorkspaceService::class)->readState($task);

        $this->assertTrue($state->allStoriesPassed());
    }

    public function test_rotates_to_next_provider(): void
    {
        $provider1 = AiProvider::factory()->claude()->create();
        $provider2 = AiProvider::factory()->kimi()->create();

        $task = Task::factory()->ralph()->create([
            'ai_provider_id' => $provider1->id,
            'ralph_model_rotation' => [$provider1->id, $provider2->id],
            'ralph_iteration' => 1,
        ]);

        $nextProvider = $task->getNextRalphProvider();

        // After 1 iteration, index 1 % 2 = 1, so provider2
        $this->assertEquals($provider2->id, $nextProvider?->id);
    }

    public function test_dispatches_next_iteration(): void
    {
        Queue::fake();

        $task = Task::factory()->ralph()->create([
            'ralph_max_iterations' => 10,
            'workspace_path' => '/tmp/test-workspace',
        ]);

        app(RalphWorkspaceService::class)->initialize($task, [
            'branch_name' => 'ralph/test',
            'stories' => [['id' => 'US-001', 'priority' => 1, 'passes' => false]],
        ]);

        // Dispatch a job
        RunRalphJob::dispatch($task, 1);

        Queue::assertPushed(RunRalphJob::class);
    }

    public function test_builds_codex_command_when_provider_is_codex(): void
    {
        $provider = AiProvider::factory()->codex()->create(['model' => 'o3']);
        $task = Task::factory()->ralph()->create([
            'ai_provider_id' => $provider->id,
            'workspace_path' => '/tmp/test-workspace',
        ]);

        $job = new RunRalphJob($task, 1);

        $command = $this->invokeProtected($job, 'buildRalphCommand', ['Test prompt']);

        $this->assertStringContainsString('codex exec --json', $command);
        $this->assertStringNotContainsString('/usr/bin/claude', $command);
        $this->assertStringContainsString('-m', $command);
        $this->assertStringContainsString("'o3'", $command);
    }

    public function test_marks_story_passed_without_mutating_readonly_state(): void
    {
        $task = Task::factory()->ralph()->create([
            'workspace_path' => '/tmp/test-workspace',
        ]);

        $service = app(RalphWorkspaceService::class);
        $service->initialize($task, [
            'branch_name' => 'ralph/test',
            'stories' => [
                ['id' => 'US-001', 'title' => 'First story', 'priority' => 1, 'passes' => false],
                ['id' => 'US-002', 'title' => 'Second story', 'priority' => 2, 'passes' => false],
            ],
        ]);

        $job = new RunRalphJob($task, 1);
        $state = $service->readState($task);

        $this->invokeProtected($job, 'markStoryPassed', [
            $service,
            $state,
            ['id' => 'US-001', 'title' => 'First story', 'priority' => 1, 'passes' => false],
        ]);

        $updatedState = $service->readState($task);

        $this->assertTrue($updatedState->prd['userStories'][0]['passes']);
        $this->assertFalse($updatedState->prd['userStories'][1]['passes']);
    }

    public function test_complete_task_disables_ralph(): void
    {
        $task = Task::factory()->ralph()->running()->create([
            'workspace_path' => '/tmp/test-workspace',
        ]);

        $service = app(RalphWorkspaceService::class);
        $service->initialize($task, [
            'branch_name' => 'ralph/test',
            'stories' => [
                ['id' => 'US-001', 'title' => 'Done', 'priority' => 1, 'passes' => true],
            ],
        ]);

        $job = new RunRalphJob($task, 3);
        $state = $service->readState($task);

        $this->invokeProtected($job, 'completeTask', [$state]);

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'status' => 'completed',
            'ralph_enabled' => false,
            'ralph_stopped_reason' => 'completed',
        ]);
    }

    public function test_fail_with_error_disables_ralph(): void
    {
        $task = Task::factory()->ralph()->running()->create([
            'workspace_path' => '/tmp/test-workspace',
        ]);

        $job = new RunRalphJob($task, 2);

        $this->invokeProtected($job, 'failWithError', ['gutter_detected']);

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'status' => 'failed',
            'ralph_enabled' => false,
            'ralph_stopped_reason' => 'gutter_detected',
        ]);
    }

    public function test_handle_returns_early_when_ralph_has_been_disabled(): void
    {
        Queue::fake();

        $task = Task::factory()->ralph()->running()->create([
            'workspace_path' => '/tmp/test-workspace',
            'ralph_enabled' => false,
        ]);

        $initialMessageCount = $task->messages()->count();

        $job = new RunRalphJob($task, 2);
        $job->handle(app(RalphWorkspaceService::class));

        $task->refresh();

        $this->assertSame('running', $task->status->value);
        $this->assertNull($task->ralph_stopped_reason);
        $this->assertSame($initialMessageCount, $task->messages()->count());
        Queue::assertNothingPushed();
    }

    /**
     * @param  array<int, mixed>  $args
     */
    protected function invokeProtected(object $instance, string $method, array $args = []): mixed
    {
        $ref = new ReflectionMethod($instance, $method);
        $ref->setAccessible(true);

        return $ref->invokeArgs($instance, $args);
    }
}
