<?php

use App\Jobs\RunClaudeMessageJob;
use App\Jobs\RunRalphJob;
use App\Livewire\TaskChat;
use App\Models\AiProvider;
use App\Models\Message;
use App\Models\Repository;
use App\Models\Site;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('can render task chat component', function () {
    $repository = Repository::factory()->create(['user_id' => $this->user->id]);
    $task = Task::factory()->create(['repository_id' => $repository->id]);

    Livewire::test(TaskChat::class, ['task' => $task])
        ->assertSuccessful()
        ->assertSee('Start a conversation');
});

test('chat prompt input is manually resizable', function () {
    $repository = Repository::factory()->create(['user_id' => $this->user->id]);
    $task = Task::factory()->create(['repository_id' => $repository->id]);

    Livewire::test(TaskChat::class, ['task' => $task])
        ->assertSeeHtml('chat-textarea-resizable');
});

test('can send a message', function () {
    Queue::fake();

    $repository = Repository::factory()->create(['user_id' => $this->user->id]);
    $task = Task::factory()->create(['repository_id' => $repository->id]);

    Livewire::test(TaskChat::class, ['task' => $task])
        ->set('prompt', 'Hello Claude!')
        ->call('sendMessage');

    expect(Message::where('content', 'Hello Claude!')->exists())->toBeTrue();
    Queue::assertPushed(RunClaudeMessageJob::class);
});

test('it automatically generates a title after the first user message', function () {
    Queue::fake();
    Http::fake([
        'https://api.kimi.com/coding/v1/messages' => Http::response([
            'content' => [
                ['text' => 'CSV Import Wizard'],
            ],
        ]),
    ]);

    AiProvider::factory()->kimi()->create(['is_active' => true]);

    $repository = Repository::factory()->create(['user_id' => $this->user->id]);
    $task = Task::factory()->create([
        'repository_id' => $repository->id,
        'title' => null,
    ]);

    Livewire::test(TaskChat::class, ['task' => $task])
        ->set('prompt', 'Build a CSV import wizard with retryable validation.')
        ->call('sendMessage');

    expect($task->fresh()->title)->toBe('CSV Import Wizard');
    Queue::assertPushed(RunClaudeMessageJob::class);
    Http::assertSentCount(1);
});

test('it does not auto generate a title when the task already has one', function () {
    Queue::fake();
    Http::fake();

    AiProvider::factory()->kimi()->create(['is_active' => true]);

    $repository = Repository::factory()->create(['user_id' => $this->user->id]);
    $task = Task::factory()->create([
        'repository_id' => $repository->id,
        'title' => 'Existing Task Title',
    ]);

    Livewire::test(TaskChat::class, ['task' => $task])
        ->set('prompt', 'Build a CSV import wizard with retryable validation.')
        ->call('sendMessage');

    expect($task->fresh()->title)->toBe('Existing Task Title');
    Http::assertNothingSent();
});

test('it does not render the manual rename chat action', function () {
    $repository = Repository::factory()->create(['user_id' => $this->user->id]);
    $task = Task::factory()->create(['repository_id' => $repository->id]);

    Message::factory()->create([
        'task_id' => $task->id,
        'role' => \App\Enums\MessageRole::User,
        'status' => \App\Enums\MessageStatus::Sent,
        'content' => 'hello from history',
    ]);

    Livewire::test(TaskChat::class, ['task' => $task])
        ->call('loadMessages')
        ->assertDontSeeHtml('wire:click="generateTitle"')
        ->assertDontSee('Rename Chat');
});

test('typing start ralph starts the ralph loop locally instead of sending a normal chat message', function () {
    Bus::fake();

    $repository = Repository::factory()->create([
        'user_id' => $this->user->id,
        'full_name' => 'nxtyou/RezensionsHeld-Dashboard',
    ]);

    $workspacePath = '/tmp/task-chat-start-ralph';
    File::deleteDirectory($workspacePath);
    File::ensureDirectoryExists($workspacePath.'/.ralph');
    File::put($workspacePath.'/.ralph/prd.json', json_encode([
        'parentIssue' => 622,
    ], JSON_PRETTY_PRINT));

    Process::fake([
        '*gh issue list*' => Process::result(output: json_encode([
            [
                'number' => 623,
                'title' => 'Foundation',
                'body' => "## Parent PRD\n#622\n\n## Acceptance criteria\n- [ ] First criterion",
            ],
        ])),
    ]);

    $task = Task::factory()->create([
        'repository_id' => $repository->id,
        'workspace_path' => $workspacePath,
        'ralph_enabled' => false,
    ]);

    Livewire::test(TaskChat::class, ['task' => $task])
        ->set('prompt', 'start ralph')
        ->call('sendMessage');

    expect($task->fresh()->ralph_enabled)->toBeTrue();
    expect($task->fresh()->ralph_branch_name)->toBe("ralph/{$task->uuid}");
    Bus::assertDispatched(RunRalphJob::class);
    Bus::assertNotDispatched(RunClaudeMessageJob::class);
    expect(Message::where('task_id', $task->id)->where('content', 'start ralph')->exists())->toBeFalse();
});

test('typing start ralph detects the parent PRD from the existing ralph prd.json structure', function () {
    Bus::fake();

    $repository = Repository::factory()->create([
        'user_id' => $this->user->id,
        'full_name' => 'nxtyou/RezensionsHeld-Dashboard',
    ]);

    $workspacePath = '/tmp/task-chat-start-ralph-nested-prd';
    File::deleteDirectory($workspacePath);
    File::ensureDirectoryExists($workspacePath.'/.ralph');
    File::put($workspacePath.'/.ralph/prd.json', json_encode([
        'prd' => [
            'issue_number' => 622,
            'title' => 'Existing PRD',
        ],
        'slices' => [],
    ], JSON_PRETTY_PRINT));

    Process::fake([
        '*gh issue list*' => Process::result(output: json_encode([
            [
                'number' => 623,
                'title' => 'Foundation',
                'body' => "## Parent PRD\n#622\n\n## Acceptance criteria\n- [ ] First criterion",
            ],
        ])),
    ]);

    $task = Task::factory()->create([
        'repository_id' => $repository->id,
        'workspace_path' => $workspacePath,
        'ralph_enabled' => false,
    ]);

    Livewire::test(TaskChat::class, ['task' => $task])
        ->set('prompt', 'start ralph')
        ->call('sendMessage');

    expect($task->fresh()->ralph_enabled)->toBeTrue();
    expect($task->fresh()->ralph_branch_name)->toBe("ralph/{$task->uuid}");
    Bus::assertDispatched(RunRalphJob::class);
    Bus::assertNotDispatched(RunClaudeMessageJob::class);
});

test('shows repository and location in header', function () {
    $repository = Repository::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'my-awesome-repo',
    ]);
    $task = Task::factory()->create([
        'repository_id' => $repository->id,
        'workspace_path' => '/home/ploi/workspaces/test',
    ]);

    Livewire::test(TaskChat::class, ['task' => $task])
        ->assertSee('my-awesome-repo')
        ->assertSee('Workspace');
});

test('shows delete workspace button for workspace tasks', function () {
    $repository = Repository::factory()->create(['user_id' => $this->user->id]);
    $task = Task::factory()->create([
        'repository_id' => $repository->id,
        'workspace_path' => '/home/ploi/workspaces/test',
    ]);

    Livewire::test(TaskChat::class, ['task' => $task])
        ->assertSee('Delete Workspace');
});

test('does not show delete workspace button for site tasks', function () {
    $repository = Repository::factory()->create(['user_id' => $this->user->id]);
    $site = Site::factory()->active()->create(['repository_id' => $repository->id]);
    $task = Task::factory()->onSite($site)->create(['repository_id' => $repository->id]);

    Livewire::test(TaskChat::class, ['task' => $task])
        ->assertDontSee('Delete Workspace');
});

test('can deploy workspace to site', function () {
    Queue::fake();

    $repository = Repository::factory()->create(['user_id' => $this->user->id]);
    $task = Task::factory()->create([
        'repository_id' => $repository->id,
        'workspace_path' => '/home/ploi/workspaces/test',
    ]);

    Livewire::test(TaskChat::class, ['task' => $task])
        ->set('deploySubdomain', 'my-feature')
        ->call('deployToSite');

    Queue::assertPushed(\App\Jobs\DeployToSiteJob::class, function ($job) {
        return $job->subdomain === 'my-feature';
    });
});

test('can insert snippet into prompt', function () {
    $repository = Repository::factory()->create(['user_id' => $this->user->id]);
    $task = Task::factory()->create(['repository_id' => $repository->id]);

    Livewire::test(TaskChat::class, ['task' => $task])
        ->dispatch('insert-snippet', content: 'Inserted snippet text')
        ->assertSet('prompt', 'Inserted snippet text');
});

test('appends snippet to existing prompt with newlines', function () {
    $repository = Repository::factory()->create(['user_id' => $this->user->id]);
    $task = Task::factory()->create(['repository_id' => $repository->id]);

    Livewire::test(TaskChat::class, ['task' => $task])
        ->set('prompt', 'Existing text')
        ->dispatch('insert-snippet', content: 'Inserted snippet')
        ->assertSet('prompt', "Existing text\n\nInserted snippet");
});

test('defers rendering existing messages until loadMessages is called', function () {
    $repository = Repository::factory()->create(['user_id' => $this->user->id]);
    $task = Task::factory()->create(['repository_id' => $repository->id]);

    Message::factory()->create([
        'task_id' => $task->id,
        'role' => \App\Enums\MessageRole::User,
        'status' => \App\Enums\MessageStatus::Sent,
        'content' => 'hello from history',
    ]);

    Livewire::test(TaskChat::class, ['task' => $task])
        ->assertDontSee('hello from history')
        ->call('loadMessages')
        ->assertSee('hello from history');
});
