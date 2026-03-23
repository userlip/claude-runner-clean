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
    Bus::fake();

    $repository = Repository::factory()->create(['user_id' => $this->user->id]);
    $task = Task::factory()->create(['repository_id' => $repository->id]);

    Livewire::test(TaskChat::class, ['task' => $task])
        ->set('prompt', 'Hello Claude!')
        ->call('sendMessage');

    expect(Message::where('content', 'Hello Claude!')->exists())->toBeTrue();
    Bus::assertDispatched(RunClaudeMessageJob::class);
});

test('it automatically generates a title after the first user message', function () {
    Bus::fake();
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
    Bus::assertDispatched(RunClaudeMessageJob::class);
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

test('typing start ralph detects the parent PRD from prd_issue in .ralph prd.json', function () {
    Bus::fake();

    $repository = Repository::factory()->create([
        'user_id' => $this->user->id,
        'full_name' => 'userlip/socialint.nxtyou.dev',
    ]);

    $workspacePath = '/tmp/task-chat-start-ralph-prd-issue-key';
    File::deleteDirectory($workspacePath);
    File::ensureDirectoryExists($workspacePath.'/.ralph');
    File::put($workspacePath.'/.ralph/prd.json', json_encode([
        'prd_issue' => 1,
        'title' => 'Social Intelligence Report Generator',
    ], JSON_PRETTY_PRINT));

    Process::fake([
        '*gh issue list*' => Process::result(output: json_encode([
            [
                'number' => 2,
                'title' => 'Foundation',
                'body' => "## Parent PRD\n#1\n\n## Acceptance criteria\n- [ ] First criterion",
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

test('typing start ralph detects the parent PRD from parent_issue.number in .ralph prd.json', function () {
    Bus::fake();

    $repository = Repository::factory()->create([
        'user_id' => $this->user->id,
        'full_name' => 'nxtyou/RezensionsHeld-Dashboard',
    ]);

    $workspacePath = '/tmp/task-chat-start-ralph-parent-issue-number';
    File::deleteDirectory($workspacePath);
    File::ensureDirectoryExists($workspacePath.'/.ralph');
    File::put($workspacePath.'/.ralph/prd.json', json_encode([
        'parent_issue' => [
            'number' => 646,
            'title' => 'Enable affiliate self-service percentage coupons',
        ],
        'labels' => ['ralph', 'prd-slice'],
        'slices' => [
            [
                'number' => 647,
                'title' => 'Add affiliate-level controls',
                'classification' => 'AFK',
                'blocked_by' => [],
                'user_stories' => [1, 2, 3, 4],
            ],
        ],
    ], JSON_PRETTY_PRINT));

    Process::fake([
        '*gh issue list*' => Process::result(output: json_encode([
            [
                'number' => 647,
                'title' => 'Add affiliate-level controls',
                'body' => "## Parent PRD\n#646\n\n## Acceptance criteria\n- [ ] First criterion",
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

test('loads the newest 100 sent messages first once messages are loaded', function () {
    $repository = Repository::factory()->create(['user_id' => $this->user->id]);
    $task = Task::factory()->create(['repository_id' => $repository->id]);

    foreach (range(1, 105) as $index) {
        Message::factory()->create([
            'task_id' => $task->id,
            'role' => \App\Enums\MessageRole::User,
            'status' => \App\Enums\MessageStatus::Sent,
            'content' => 'message '.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
        ]);
    }

    Livewire::test(TaskChat::class, ['task' => $task])
        ->call('loadMessages')
        ->assertDontSee('message 001')
        ->assertDontSee('message 005')
        ->assertSee('message 006')
        ->assertSee('message 105');
});

test('can load older sent messages in 100 message batches', function () {
    $repository = Repository::factory()->create(['user_id' => $this->user->id]);
    $task = Task::factory()->create(['repository_id' => $repository->id]);

    foreach (range(1, 205) as $index) {
        Message::factory()->create([
            'task_id' => $task->id,
            'role' => \App\Enums\MessageRole::User,
            'status' => \App\Enums\MessageStatus::Sent,
            'content' => 'message '.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
        ]);
    }

    Livewire::test(TaskChat::class, ['task' => $task])
        ->call('loadMessages')
        ->assertDontSee('message 001')
        ->assertDontSee('message 100')
        ->assertSee('message 106')
        ->assertSee('message 205')
        ->call('loadMoreMessages')
        ->assertDontSee('message 001')
        ->assertSee('message 006')
        ->assertSee('message 205')
        ->call('loadMoreMessages')
        ->assertSee('message 001')
        ->assertSee('message 205');
});

test('stopRunning disables Ralph even when the task is already marked completed', function () {
    $provider = AiProvider::factory()->codex()->create();
    $repository = Repository::factory()->create(['user_id' => $this->user->id]);
    $task = Task::factory()->create([
        'repository_id' => $repository->id,
        'ai_provider_id' => $provider->id,
        'status' => \App\Enums\TaskStatus::Completed,
        'ralph_enabled' => true,
        'has_active_subagents' => false,
        'session_id' => (string) str()->uuid(),
        'workspace_path' => null,
    ]);

    Livewire::test(TaskChat::class, ['task' => $task])
        ->call('stopRunning');

    expect($task->fresh()->ralph_enabled)->toBeFalse()
        ->and($task->fresh()->ralph_stopped_reason)->toBe('stopped_by_user');
});

test('stopRunning terminates the tracked Ralph process tree when present', function () {
    Process::fake();

    $provider = AiProvider::factory()->codex()->create();
    $repository = Repository::factory()->create(['user_id' => $this->user->id]);
    $task = Task::factory()->create([
        'repository_id' => $repository->id,
        'ai_provider_id' => $provider->id,
        'status' => \App\Enums\TaskStatus::Completed,
        'ralph_enabled' => true,
        'has_active_subagents' => false,
        'session_id' => (string) str()->uuid(),
        'workspace_path' => null,
        'session_metadata' => [
            'ralph_process' => [
                'pid' => 4321,
                'iteration' => 2,
            ],
        ],
    ]);

    Livewire::test(TaskChat::class, ['task' => $task])
        ->call('stopRunning');

    Process::assertRan(function ($process) {
        $command = is_array($process->command) ? implode(' ', $process->command) : $process->command;

        return str_contains($command, 'pkill -TERM -P 4321');
    });

    Process::assertRan(function ($process) {
        $command = is_array($process->command) ? implode(' ', $process->command) : $process->command;

        return str_contains($command, 'kill -TERM 4321');
    });

    expect(data_get($task->fresh()->session_metadata, 'ralph_process'))->toBeNull();
});

test('does not add standalone polling to the context usage indicators', function () {
    $repository = Repository::factory()->create(['user_id' => $this->user->id]);
    $task = Task::factory()->create(['repository_id' => $repository->id]);

    $html = Livewire::test(TaskChat::class, ['task' => $task])->html();

    expect($html)->not->toMatch('/class="chat-mobile-context"[^>]*wire:poll\.5s/');
    expect($html)->not->toMatch('/class="chat-context-usage"[^>]*wire:poll\.5s/');
});

test('keeps the main chat poll for active tasks', function () {
    $repository = Repository::factory()->create(['user_id' => $this->user->id]);
    $task = Task::factory()->create([
        'repository_id' => $repository->id,
        'status' => \App\Enums\TaskStatus::Running,
    ]);

    $html = Livewire::test(TaskChat::class, ['task' => $task])->html();

    expect($html)->toContain('wire:poll.2s.visible="checkPolling"');
});
