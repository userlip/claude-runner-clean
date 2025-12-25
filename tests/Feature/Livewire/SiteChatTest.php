<?php

use App\Jobs\RunClaudeMessageJob;
use App\Livewire\SiteChat;
use App\Models\Message;
use App\Models\Site;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('can render chat component', function () {
    $site = Site::factory()->active()->create();

    Livewire::test(SiteChat::class, ['site' => $site])
        ->assertSuccessful()
        ->assertSee('Start a conversation');
});

test('can send a message', function () {
    Queue::fake();

    $site = Site::factory()->active()->create();

    Livewire::test(SiteChat::class, ['site' => $site])
        ->set('prompt', 'Hello Claude!')
        ->call('sendMessage');

    expect(Task::where('site_id', $site->id)->exists())->toBeTrue();
    expect(Message::where('content', 'Hello Claude!')->exists())->toBeTrue();

    Queue::assertPushed(RunClaudeMessageJob::class);
});

test('shows existing messages', function () {
    $site = Site::factory()->active()->create();
    $task = Task::factory()->create(['site_id' => $site->id]);
    Message::factory()->user()->create([
        'task_id' => $task->id,
        'content' => 'Test message',
    ]);

    Livewire::test(SiteChat::class, ['site' => $site])
        ->assertSee('Test message');
});

test('can start new chat', function () {
    Queue::fake();

    $site = Site::factory()->active()->create();
    Task::factory()->create(['site_id' => $site->id]);

    Livewire::test(SiteChat::class, ['site' => $site])
        ->call('newChat')
        ->set('prompt', 'New conversation')
        ->call('sendMessage');

    expect(Task::where('site_id', $site->id)->count())->toBe(2);
});

test('can select different task', function () {
    $site = Site::factory()->active()->create();
    $task1 = Task::factory()->create(['site_id' => $site->id]);
    $task2 = Task::factory()->create(['site_id' => $site->id]);

    Message::factory()->user()->create(['task_id' => $task1->id, 'content' => 'Task 1 message']);
    Message::factory()->user()->create(['task_id' => $task2->id, 'content' => 'Task 2 message']);

    Livewire::test(SiteChat::class, ['site' => $site])
        ->call('selectTask', $task1->id)
        ->assertSee('Task 1 message');
});

test('validates prompt is required', function () {
    $site = Site::factory()->active()->create();

    Livewire::test(SiteChat::class, ['site' => $site])
        ->set('prompt', '')
        ->call('sendMessage')
        ->assertHasErrors(['prompt' => 'required']);
});

test('validates prompt max length', function () {
    $site = Site::factory()->active()->create();

    Livewire::test(SiteChat::class, ['site' => $site])
        ->set('prompt', str_repeat('a', 10001))
        ->call('sendMessage')
        ->assertHasErrors(['prompt' => 'max']);
});

test('dispatches job with continue flag for subsequent messages', function () {
    Queue::fake();

    $site = Site::factory()->active()->create();
    $task = Task::factory()->create(['site_id' => $site->id]);
    Message::factory()->user()->create(['task_id' => $task->id]);

    Livewire::test(SiteChat::class, ['site' => $site])
        ->set('prompt', 'Follow up message')
        ->call('sendMessage');

    Queue::assertPushed(function (RunClaudeMessageJob $job) {
        return $job->continue === true;
    });
});

test('dispatches job without continue flag for first message', function () {
    Queue::fake();

    $site = Site::factory()->active()->create();

    Livewire::test(SiteChat::class, ['site' => $site])
        ->set('prompt', 'First message')
        ->call('sendMessage');

    Queue::assertPushed(function (RunClaudeMessageJob $job) {
        return $job->continue === false;
    });
});
