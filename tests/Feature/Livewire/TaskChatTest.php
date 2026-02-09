<?php

use App\Jobs\RunClaudeMessageJob;
use App\Livewire\TaskChat;
use App\Models\Message;
use App\Models\Repository;
use App\Models\Site;
use App\Models\Task;
use App\Models\User;
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
