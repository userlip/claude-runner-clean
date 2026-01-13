<?php

namespace Tests\Unit\Livewire;

use App\Livewire\RalphControlPanel;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class RalphControlPanelTest extends TestCase
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

    public function test_mounts_with_task_data(): void
    {
        $task = Task::factory()->ralph()->create([
            'ralph_enabled' => true,
            'ralph_max_iterations' => 50,
            'workspace_path' => '/tmp/test-workspace',
        ]);

        $component = new RalphControlPanel;
        $component->task = $task;
        $component->mount();

        $this->assertTrue($component->ralphEnabled);
        $this->assertEquals(50, $component->ralphMaxIterations);
    }

    public function test_enables_ralph_mode(): void
    {
        $task = Task::factory()->create([
            'workspace_path' => '/tmp/test-workspace',
        ]);

        $component = new RalphControlPanel;
        $component->task = $task;
        $component->mount();

        $component->ralphMaxIterations = 25;
        $component->ralphRotationThreshold = 0.7;
        $component->ralphBranchName = 'ralph/test-feature';
        $component->verificationCommand = 'php artisan test';
        $component->userStories = [
            ['id' => 'US-001', 'title' => 'Test', 'priority' => 1, 'passes' => false],
        ];

        $component->enableRalph();

        $this->assertTrue($component->ralphEnabled);

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'ralph_enabled' => true,
            'ralph_max_iterations' => 25,
            'ralph_rotation_threshold' => 0.7,
        ]);
    }

    public function test_disables_ralph_mode(): void
    {
        $task = Task::factory()->ralph()->create([
            'ralph_enabled' => true,
            'workspace_path' => '/tmp/test-workspace',
        ]);

        $component = new RalphControlPanel;
        $component->task = $task;
        $component->mount();

        $component->disableRalph();

        $this->assertFalse($component->ralphEnabled);

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'ralph_enabled' => false,
        ]);
    }

    public function test_starts_ralph_job(): void
    {
        $task = Task::factory()->ralph()->create([
            'workspace_path' => '/tmp/test-workspace',
        ]);

        \Illuminate\Support\Facades\Bus::fake();

        $component = new RalphControlPanel;
        $component->task = $task;
        $component->mount();

        $component->startRalph();

        \Illuminate\Support\Facades\Bus::assertDispatched(\App\Jobs\RunRalphJob::class);
    }

    public function test_ralph_status_logic(): void
    {
        $task = Task::factory()->ralph()->create([
            'ralph_enabled' => true,
            'ralph_iteration' => 5,
            'ralph_max_iterations' => 25,
            'workspace_path' => '/tmp/test-workspace',
        ]);

        app(\App\Services\RalphWorkspaceService::class)->initialize($task, [
            'branch_name' => 'ralph/test',
            'stories' => [
                ['id' => 'US-001', 'priority' => 1, 'passes' => true],
                ['id' => 'US-002', 'priority' => 2, 'passes' => false],
            ],
        ]);

        // Test by calling the method directly rather than accessing computed property
        $state = $task->getRalphState();

        $this->assertEquals(2, count($state->prd['userStories']));
        $this->assertTrue($state->prd['userStories'][0]['passes']);
        $this->assertFalse($state->prd['userStories'][1]['passes']);
    }

    public function test_ralph_disabled_status(): void
    {
        $task = Task::factory()->create([
            'ralph_enabled' => false,
            'workspace_path' => '/tmp/test-workspace',
        ]);

        $component = new RalphControlPanel;
        $component->task = $task;
        $component->mount();

        $this->assertFalse($component->ralphEnabled);
    }

    public function test_max_iterations_defaults_to_25(): void
    {
        $task = Task::factory()->create([
            'workspace_path' => '/tmp/test-workspace',
        ]);

        $component = new RalphControlPanel;
        $component->task = $task;
        $component->mount();

        $this->assertEquals(25, $component->ralphMaxIterations);
    }
}
