<?php

use App\Filament\Resources\ProposalResource\Pages\ViewProposal;
use App\Jobs\CloneRepositoryJob;
use App\Jobs\GeneratePersonaSubtasksJob;
use App\Models\Persona;
use App\Models\Proposal;
use App\Models\Repository;
use App\Models\Task;
use App\Models\User;
use App\Services\ProposalExecutionService;
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

test('approving a persona proposal triggers subtask generation not direct task execution', function () {
    $proposal = Proposal::factory()->create([
        'persona_id' => $this->persona->id,
        'project' => 'test_project',
        'description' => 'Analyze SEO performance',
    ]);

    $telegramService = Mockery::mock(TelegramService::class);
    $telegramService->shouldReceive('sendPlainMessage')->zeroOrMoreTimes();
    $telegramService->shouldReceive('sendProposalNotification')->zeroOrMoreTimes();
    $telegramService->shouldReceive('updateProposalMessage')->zeroOrMoreTimes();
    app()->instance(TelegramService::class, $telegramService);

    $proposal->approve();

    $proposal->refresh();
    expect($proposal->isApproved())->toBeTrue();
    expect($proposal->executed_task_id)->not->toBeNull();

    // Should dispatch CloneRepositoryJob which chains GeneratePersonaSubtasksJob
    Queue::assertPushed(CloneRepositoryJob::class);

    // Should NOT dispatch RunClaudeMessageJob or RunCodexMessageJob directly
    Queue::assertNotPushed(\App\Jobs\RunClaudeMessageJob::class);
    Queue::assertNotPushed(\App\Jobs\RunCodexMessageJob::class);
});

test('ProposalExecutionService forks to persona flow when persona_id is present', function () {
    $proposal = Proposal::factory()->approved()->create([
        'persona_id' => $this->persona->id,
        'project' => 'test_project',
        'description' => 'Improve website performance',
    ]);

    $service = app(ProposalExecutionService::class);
    $task = $service->execute($proposal);

    expect($task)->toBeInstanceOf(Task::class);
    expect($task->title)->toContain('[Persona]');
    expect($task->repository_id)->toBe($this->repository->id);

    $proposal->refresh();
    expect($proposal->executed_task_id)->toBe($task->id);

    // Should dispatch CloneRepositoryJob (which chains GeneratePersonaSubtasksJob)
    Queue::assertPushed(CloneRepositoryJob::class);
});

test('ProposalExecutionService uses standard flow when no persona_id', function () {
    $proposal = Proposal::factory()->approved()->create([
        'project' => 'test_project',
        'description' => 'Regular task',
    ]);

    $service = app(ProposalExecutionService::class);
    $task = $service->execute($proposal);

    expect($task)->toBeInstanceOf(Task::class);
    expect($task->title)->not->toContain('Persona');
});

test('generated subtasks are valid JSON with correct structure', function () {
    $job = new GeneratePersonaSubtasksJob(
        Proposal::factory()->approved()->create([
            'persona_id' => $this->persona->id,
            'project' => 'test_project',
        ]),
        Task::factory()->create([
            'user_id' => $this->user->id,
            'repository_id' => $this->repository->id,
        ])
    );

    // Test the parseSubtasks method via reflection
    $reflection = new ReflectionClass($job);
    $method = $reflection->getMethod('parseSubtasks');
    $method->setAccessible(true);

    // Test with fenced JSON block
    $responseText = <<<'TEXT'
    Here are the subtasks:

    ```json
    [
        {"title": "Analyze current meta tags", "description": "Review all page meta tags for SEO compliance"},
        {"title": "Fix broken links", "description": "Identify and fix broken internal links"},
        {"title": "Optimize images", "description": "Compress and add alt text to images"}
    ]
    ```

    These subtasks cover the main areas.
    TEXT;

    $subtasks = $method->invoke($job, $responseText);

    expect($subtasks)->toBeArray();
    expect($subtasks)->toHaveCount(3);

    // Verify structure
    foreach ($subtasks as $i => $subtask) {
        expect($subtask)->toHaveKeys(['index', 'title', 'description', 'status']);
        expect($subtask['index'])->toBe($i);
        expect($subtask['status'])->toBe('pending');
        expect($subtask['title'])->toBeString();
        expect($subtask['description'])->toBeString();
    }

    expect($subtasks[0]['title'])->toBe('Analyze current meta tags');
    expect($subtasks[1]['title'])->toBe('Fix broken links');
    expect($subtasks[2]['title'])->toBe('Optimize images');
});

test('parseSubtasks handles raw JSON array without code fences', function () {
    $job = new GeneratePersonaSubtasksJob(
        Proposal::factory()->approved()->create([
            'persona_id' => $this->persona->id,
            'project' => 'test_project',
        ]),
        Task::factory()->create([
            'user_id' => $this->user->id,
            'repository_id' => $this->repository->id,
        ])
    );

    $reflection = new ReflectionClass($job);
    $method = $reflection->getMethod('parseSubtasks');
    $method->setAccessible(true);

    $responseText = '[{"title": "Task A", "description": "Do A"}, {"title": "Task B", "description": "Do B"}]';

    $subtasks = $method->invoke($job, $responseText);

    expect($subtasks)->toHaveCount(2);
    expect($subtasks[0]['title'])->toBe('Task A');
    expect($subtasks[0]['status'])->toBe('pending');
    expect($subtasks[0]['index'])->toBe(0);
});

test('approving subtasks via Filament action sets subtasks_approved_at', function () {
    $telegramService = Mockery::mock(TelegramService::class);
    $telegramService->shouldReceive('sendPlainMessage')->zeroOrMoreTimes();
    app()->instance(TelegramService::class, $telegramService);

    $proposal = Proposal::factory()->approved()->create([
        'persona_id' => $this->persona->id,
        'project' => 'test_project',
        'subtasks' => [
            ['index' => 0, 'title' => 'First task', 'description' => 'Do first thing', 'status' => 'pending'],
            ['index' => 1, 'title' => 'Second task', 'description' => 'Do second thing', 'status' => 'pending'],
        ],
        'subtasks_approved_at' => null,
        'current_subtask_index' => 0,
    ]);

    expect($proposal->hasSubtasksPendingApproval())->toBeTrue();

    livewire(ViewProposal::class, ['record' => $proposal->getRouteKey()])
        ->assertActionVisible('approve_subtasks')
        ->callAction('approve_subtasks')
        ->assertNotified('Subtasks approved — execution starting');

    $proposal->refresh();
    expect($proposal->subtasks_approved_at)->not->toBeNull();

    // Should dispatch startSubtaskExecution which creates a task and dispatches CloneRepositoryJob
    Queue::assertPushed(CloneRepositoryJob::class);
});

test('approve subtasks action hidden when subtasks already approved', function () {
    $proposal = Proposal::factory()->approved()->create([
        'persona_id' => $this->persona->id,
        'subtasks' => [
            ['index' => 0, 'title' => 'Task', 'description' => 'Do thing', 'status' => 'pending'],
        ],
        'subtasks_approved_at' => now(),
    ]);

    livewire(ViewProposal::class, ['record' => $proposal->getRouteKey()])
        ->assertActionHidden('approve_subtasks');
});

test('approve subtasks action hidden when no subtasks', function () {
    $proposal = Proposal::factory()->approved()->create([
        'persona_id' => $this->persona->id,
        'subtasks' => null,
    ]);

    livewire(ViewProposal::class, ['record' => $proposal->getRouteKey()])
        ->assertActionHidden('approve_subtasks');
});

test('subtask generation prompt includes proposal description, data appendix, and persona context', function () {
    $persona = Persona::factory()->create([
        'user_id' => $this->user->id,
        'repository_id' => $this->repository->id,
        'master_prompt' => 'You are an SEO specialist.',
        'mcp_guidance' => 'Use Google Analytics MCP to fetch data.',
    ]);

    $proposal = Proposal::factory()->approved()->create([
        'persona_id' => $persona->id,
        'project' => 'test_project',
        'description' => 'Analyze SEO for the homepage',
        'data_appendix' => 'Current PageSpeed score: 65/100',
    ]);

    $task = Task::factory()->create([
        'user_id' => $this->user->id,
        'repository_id' => $this->repository->id,
    ]);

    $job = new GeneratePersonaSubtasksJob($proposal, $task);

    $reflection = new ReflectionClass($job);
    $method = $reflection->getMethod('buildPrompt');
    $method->setAccessible(true);

    $prompt = $method->invoke($job);

    expect($prompt)->toContain('Analyze SEO for the homepage');
    expect($prompt)->toContain('Current PageSpeed score: 65/100');
    expect($prompt)->toContain('You are an SEO specialist.');
    expect($prompt)->toContain('Use Google Analytics MCP to fetch data.');
    expect($prompt)->toContain($persona->name);

    // Cleanup
    $path = $persona->getStoragePath();
    if (File::isDirectory($path)) {
        File::deleteDirectory($path);
    }
});
