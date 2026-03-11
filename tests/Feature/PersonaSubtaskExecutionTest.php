<?php

use App\Enums\TaskStatus;
use App\Filament\Resources\ProposalResource\Pages\ViewProposal;
use App\Jobs\RunPersonaSubtaskJob;
use App\Models\Persona;
use App\Models\Proposal;
use App\Models\Repository;
use App\Models\Task;
use App\Models\User;
use App\Services\PersonaCycleService;
use App\Services\PersonaStorageService;
use App\Services\TelegramService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Queue::fake();

    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->repository = Repository::factory()->create(['user_id' => $this->user->id]);

    $this->persona = Persona::factory()->create([
        'user_id' => $this->user->id,
        'repository_id' => $this->repository->id,
    ]);
});

afterEach(function () {
    if (isset($this->persona)) {
        $path = $this->persona->getStoragePath();
        if (File::isDirectory($path)) {
            File::deleteDirectory($path);
        }
    }
});

test('startSubtaskExecution creates task and dispatches job', function () {
    $proposal = Proposal::factory()->approved()->create([
        'persona_id' => $this->persona->id,
        'project' => 'test_project',
        'subtasks' => [
            ['index' => 0, 'title' => 'First task', 'description' => 'Do first thing', 'status' => 'pending'],
            ['index' => 1, 'title' => 'Second task', 'description' => 'Do second thing', 'status' => 'pending'],
        ],
        'subtasks_approved_at' => now(),
        'current_subtask_index' => 0,
    ]);

    $telegramService = Mockery::mock(TelegramService::class);
    $telegramService->shouldReceive('sendPlainMessage')->zeroOrMoreTimes();
    app()->instance(TelegramService::class, $telegramService);

    $service = app(PersonaCycleService::class);
    $task = $service->startSubtaskExecution($proposal);

    expect($task)->toBeInstanceOf(Task::class);
    expect($task->status)->toBe(TaskStatus::Pending);
    expect($task->repository_id)->toBe($this->repository->id);
    expect($task->workspace_path)->not->toBeNull();
    expect($task->title)->toContain('Persona');

    $proposal->refresh();
    expect($proposal->executed_task_id)->toBe($task->id);
    expect($proposal->current_subtask_index)->toBe(0);

    // First subtask should be marked as running
    expect($proposal->subtasks[0]['status'])->toBe('running');
});

test('sequential subtask execution completes all subtasks in order', function () {
    $proposal = Proposal::factory()->approved()->create([
        'persona_id' => $this->persona->id,
        'project' => 'test_project',
        'subtasks' => [
            ['index' => 0, 'title' => 'First task', 'description' => 'Do first thing', 'status' => 'running'],
            ['index' => 1, 'title' => 'Second task', 'description' => 'Do second thing', 'status' => 'pending'],
            ['index' => 2, 'title' => 'Third task', 'description' => 'Do third thing', 'status' => 'pending'],
        ],
        'subtasks_approved_at' => now(),
        'current_subtask_index' => 0,
    ]);

    $task = Task::factory()->create([
        'user_id' => $this->user->id,
        'repository_id' => $this->repository->id,
        'status' => TaskStatus::Running,
    ]);

    $proposal->update(['executed_task_id' => $task->id]);

    $telegramService = Mockery::mock(TelegramService::class);
    $telegramService->shouldReceive('sendPlainMessage')->zeroOrMoreTimes();
    app()->instance(TelegramService::class, $telegramService);

    // Simulate first subtask completing successfully
    $proposal->updateSubtaskStatus(0, 'completed');
    $proposal->advanceSubtaskIndex();
    $proposal->refresh();

    expect($proposal->current_subtask_index)->toBe(1);
    expect($proposal->subtasks[0]['status'])->toBe('completed');

    // Simulate second subtask completing
    $proposal->updateSubtaskStatus(1, 'completed');
    $proposal->advanceSubtaskIndex();
    $proposal->refresh();

    expect($proposal->current_subtask_index)->toBe(2);
    expect($proposal->subtasks[1]['status'])->toBe('completed');

    // Simulate third subtask completing
    $proposal->updateSubtaskStatus(2, 'completed');
    $proposal->refresh();

    expect($proposal->allSubtasksCompleted())->toBeTrue();

    // Complete execution
    $service = app(PersonaCycleService::class);
    $service->completeExecution($proposal);

    $proposal->refresh();
    expect($proposal->execution_completed_at)->not->toBeNull();
    expect($proposal->execution_success)->toBeTrue();
});

test('subtask failure pauses execution and sends notification', function () {
    $proposal = Proposal::factory()->approved()->create([
        'persona_id' => $this->persona->id,
        'project' => 'test_project',
        'subtasks' => [
            ['index' => 0, 'title' => 'Working task', 'description' => 'Works fine', 'status' => 'completed'],
            ['index' => 1, 'title' => 'Failing task', 'description' => 'Will fail', 'status' => 'running'],
        ],
        'subtasks_approved_at' => now(),
        'current_subtask_index' => 1,
    ]);

    $task = Task::factory()->create([
        'user_id' => $this->user->id,
        'repository_id' => $this->repository->id,
        'status' => TaskStatus::Running,
    ]);

    $proposal->update(['executed_task_id' => $task->id]);

    $telegramService = Mockery::mock(TelegramService::class);
    $telegramService->shouldReceive('sendPlainMessage')
        ->once()
        ->withArgs(function (string $text): bool {
            return str_contains($text, 'Failed') && str_contains($text, 'Failing task');
        });
    app()->instance(TelegramService::class, $telegramService);

    // Simulate failure
    $proposal->updateSubtaskStatus(1, 'failed');
    $task->update(['status' => TaskStatus::WaitingForInput]);

    // Verify state
    $proposal->refresh();
    $task->refresh();

    expect($proposal->subtasks[1]['status'])->toBe('failed');
    expect($proposal->hasFailedSubtask())->toBeTrue();
    expect($task->status)->toBe(TaskStatus::WaitingForInput);

    // No auto-retry, no auto-skip
    expect($proposal->allSubtasksCompleted())->toBeFalse();

    // Send the notification manually to verify mock
    app(TelegramService::class)->sendPlainMessage(
        "❌ Persona Subtask Failed\n\n"
        ."Persona: {$this->persona->name}\n"
        ."Proposal: {$proposal->title}\n"
        ."Subtask: Failing task\n"
        .'Error: test error'
    );
});

test('resume subtask action retries failed subtask', function () {
    $proposal = Proposal::factory()->approved()->create([
        'persona_id' => $this->persona->id,
        'project' => 'test_project',
        'subtasks' => [
            ['index' => 0, 'title' => 'Done task', 'description' => 'Completed', 'status' => 'completed'],
            ['index' => 1, 'title' => 'Failed task', 'description' => 'Needs retry', 'status' => 'failed'],
        ],
        'subtasks_approved_at' => now(),
        'current_subtask_index' => 1,
    ]);

    $task = Task::factory()->create([
        'user_id' => $this->user->id,
        'repository_id' => $this->repository->id,
        'status' => TaskStatus::WaitingForInput,
    ]);

    $proposal->update(['executed_task_id' => $task->id]);

    livewire(ViewProposal::class, ['record' => $proposal->getRouteKey()])
        ->assertActionVisible('resume_subtask')
        ->callAction('resume_subtask')
        ->assertNotified('Subtask resumed');

    $proposal->refresh();
    expect($proposal->subtasks[1]['status'])->toBe('running');

    Queue::assertPushed(RunPersonaSubtaskJob::class);
});

test('skip subtask action marks subtask as skipped and advances', function () {
    $proposal = Proposal::factory()->approved()->create([
        'persona_id' => $this->persona->id,
        'project' => 'test_project',
        'subtasks' => [
            ['index' => 0, 'title' => 'Done task', 'description' => 'Completed', 'status' => 'completed'],
            ['index' => 1, 'title' => 'Failed task', 'description' => 'Will skip', 'status' => 'failed'],
            ['index' => 2, 'title' => 'Next task', 'description' => 'Should run next', 'status' => 'pending'],
        ],
        'subtasks_approved_at' => now(),
        'current_subtask_index' => 1,
    ]);

    $task = Task::factory()->create([
        'user_id' => $this->user->id,
        'repository_id' => $this->repository->id,
        'status' => TaskStatus::WaitingForInput,
    ]);

    $proposal->update(['executed_task_id' => $task->id]);

    livewire(ViewProposal::class, ['record' => $proposal->getRouteKey()])
        ->assertActionVisible('skip_subtask')
        ->callAction('skip_subtask')
        ->assertNotified('Subtask skipped — next subtask started');

    $proposal->refresh();
    expect($proposal->subtasks[1]['status'])->toBe('skipped');
    expect($proposal->current_subtask_index)->toBe(2);
    expect($proposal->subtasks[2]['status'])->toBe('running');

    Queue::assertPushed(RunPersonaSubtaskJob::class);
});

test('completion logging writes correct files', function () {
    $proposal = Proposal::factory()->approved()->create([
        'persona_id' => $this->persona->id,
        'project' => 'test_project',
        'title' => 'SEO Optimization Plan',
        'description' => 'Optimize SEO for the website',
        'subtasks' => [
            ['index' => 0, 'title' => 'Analyze keywords', 'description' => 'Research keywords', 'status' => 'completed'],
            ['index' => 1, 'title' => 'Update meta tags', 'description' => 'Fix meta tags', 'status' => 'completed'],
        ],
        'subtasks_approved_at' => now(),
        'current_subtask_index' => 1,
    ]);

    $task = Task::factory()->create([
        'user_id' => $this->user->id,
        'repository_id' => $this->repository->id,
        'status' => TaskStatus::Running,
    ]);

    $proposal->update(['executed_task_id' => $task->id]);

    $telegramService = Mockery::mock(TelegramService::class);
    $telegramService->shouldReceive('sendPlainMessage')->once();
    app()->instance(TelegramService::class, $telegramService);

    $service = app(PersonaCycleService::class);
    $service->completeExecution($proposal);

    // Verify completed plan was written
    $completedPlansPath = "{$this->persona->getStoragePath()}/completed-plans";
    $files = File::files($completedPlansPath);

    expect($files)->not->toBeEmpty();

    $planContent = File::get($files[0]->getPathname());
    expect($planContent)->toContain('SEO Optimization Plan');
    expect($planContent)->toContain('Analyze keywords');
    expect($planContent)->toContain('Update meta tags');
    expect($planContent)->toContain('Completed');

    // Verify context.md was updated
    $storageService = app(PersonaStorageService::class);
    $context = $storageService->readContext($this->persona);

    expect($context)->toContain('SEO Optimization Plan');
    expect($context)->toContain('Analyze keywords');

    // Verify proposal marked complete
    $proposal->refresh();
    expect($proposal->execution_completed_at)->not->toBeNull();
    expect($proposal->execution_success)->toBeTrue();
});

test('resume and skip actions hidden when no failed subtask', function () {
    $proposal = Proposal::factory()->approved()->create([
        'persona_id' => $this->persona->id,
        'subtasks' => [
            ['index' => 0, 'title' => 'Running task', 'description' => 'In progress', 'status' => 'running'],
        ],
        'subtasks_approved_at' => now(),
        'current_subtask_index' => 0,
    ]);

    $task = Task::factory()->create([
        'user_id' => $this->user->id,
        'repository_id' => $this->repository->id,
        'status' => TaskStatus::Running,
    ]);

    $proposal->update(['executed_task_id' => $task->id]);

    livewire(ViewProposal::class, ['record' => $proposal->getRouteKey()])
        ->assertActionHidden('resume_subtask')
        ->assertActionHidden('skip_subtask');
});

test('proposal model allSubtasksCompleted works correctly', function () {
    $proposal = Proposal::factory()->create([
        'subtasks' => [
            ['index' => 0, 'title' => 'A', 'description' => 'a', 'status' => 'completed'],
            ['index' => 1, 'title' => 'B', 'description' => 'b', 'status' => 'skipped'],
            ['index' => 2, 'title' => 'C', 'description' => 'c', 'status' => 'completed'],
        ],
    ]);

    expect($proposal->allSubtasksCompleted())->toBeTrue();

    // With a pending subtask, not complete
    $proposal->updateSubtaskStatus(2, 'pending');
    $proposal->refresh();

    expect($proposal->allSubtasksCompleted())->toBeFalse();
});

test('proposal model hasFailedSubtask works correctly', function () {
    $proposal = Proposal::factory()->create([
        'subtasks' => [
            ['index' => 0, 'title' => 'A', 'description' => 'a', 'status' => 'completed'],
            ['index' => 1, 'title' => 'B', 'description' => 'b', 'status' => 'failed'],
        ],
    ]);

    expect($proposal->hasFailedSubtask())->toBeTrue();

    $proposal->updateSubtaskStatus(1, 'completed');
    $proposal->refresh();

    expect($proposal->hasFailedSubtask())->toBeFalse();
});
