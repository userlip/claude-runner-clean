<?php

use App\Enums\TaskStatus;
use App\Models\Message;
use App\Models\Site;
use App\Models\Task;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('can fetch task messages', function () {
    $site = Site::factory()->active()->create();
    $task = Task::factory()->create(['site_id' => $site->id]);
    Message::factory()->user()->create(['task_id' => $task->id]);
    Message::factory()->assistant()->create(['task_id' => $task->id]);

    $response = $this->getJson(route('api.tasks.messages', $task));

    $response->assertOk()
        ->assertJsonCount(2, 'messages')
        ->assertJsonPath('task.uuid', (string) $task->uuid);
});

test('can fetch messages since id', function () {
    $site = Site::factory()->active()->create();
    $task = Task::factory()->create(['site_id' => $site->id]);
    $message1 = Message::factory()->create(['task_id' => $task->id]);
    $message2 = Message::factory()->create(['task_id' => $task->id]);

    $response = $this->getJson(route('api.tasks.messages', [
        'task' => $task,
        'since' => $message1->id,
    ]));

    $response->assertOk()
        ->assertJsonCount(1, 'messages')
        ->assertJsonPath('messages.0.id', $message2->id);
});

test('returns task status', function () {
    $site = Site::factory()->active()->create();
    $task = Task::factory()->running()->create(['site_id' => $site->id]);

    $response = $this->getJson(route('api.tasks.messages', $task));

    $response->assertOk()
        ->assertJsonPath('task.status', TaskStatus::Running->value);
});

test('requires authentication', function () {
    // Create a new test instance without authentication
    $site = Site::factory()->active()->create();
    $task = Task::factory()->create(['site_id' => $site->id]);

    // Make an unauthenticated request
    $this->app['auth']->forgetGuards();

    $response = $this->getJson(route('api.tasks.messages', $task));

    $response->assertUnauthorized();
});

test('returns messages in oldest order', function () {
    $site = Site::factory()->active()->create();
    $task = Task::factory()->create(['site_id' => $site->id]);

    $message1 = Message::factory()->create([
        'task_id' => $task->id,
        'created_at' => now()->subMinutes(2),
    ]);
    $message2 = Message::factory()->create([
        'task_id' => $task->id,
        'created_at' => now()->subMinute(),
    ]);
    $message3 = Message::factory()->create([
        'task_id' => $task->id,
        'created_at' => now(),
    ]);

    $response = $this->getJson(route('api.tasks.messages', $task));

    $response->assertOk()
        ->assertJsonPath('messages.0.id', $message1->id)
        ->assertJsonPath('messages.1.id', $message2->id)
        ->assertJsonPath('messages.2.id', $message3->id);
});

test('returns message fields', function () {
    $site = Site::factory()->active()->create();
    $task = Task::factory()->create(['site_id' => $site->id]);
    Message::factory()->assistant()->withToolCalls()->create([
        'task_id' => $task->id,
        'content' => 'Test content',
        'tokens_in' => 100,
        'tokens_out' => 500,
        'cost_usd' => 0.0025,
    ]);

    $response = $this->getJson(route('api.tasks.messages', $task));

    $response->assertOk()
        ->assertJsonPath('messages.0.content', 'Test content')
        ->assertJsonPath('messages.0.role', 'assistant')
        ->assertJsonPath('messages.0.tokens_in', 100)
        ->assertJsonPath('messages.0.tokens_out', 500);

    expect($response->json('messages.0.tool_calls'))->not->toBeNull();
    expect($response->json('messages.0.created_at'))->not->toBeNull();
});
