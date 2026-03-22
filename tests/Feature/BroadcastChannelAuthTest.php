<?php

use App\Models\Repository;
use App\Models\Task;
use App\Models\User;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->other = User::factory()->create();
});

/**
 * Helper to call the broadcast auth endpoint.
 */
function broadcastAuth(string $channelName): \Illuminate\Testing\TestResponse
{
    return test()->post('/broadcasting/auth', [
        'channel_name' => $channelName,
        'socket_id' => '1234.5678',
    ]);
}

// ── users.{userId} channel ────────────────────────────────────────────────────

test('users channel: correct user receives 200', function () {
    $this->actingAs($this->owner)
        ->post('/broadcasting/auth', [
            'channel_name' => 'private-users.'.$this->owner->id,
            'socket_id' => '1234.5678',
        ])
        ->assertOk();
});

test('users channel: wrong user receives 403', function () {
    $this->actingAs($this->other)
        ->post('/broadcasting/auth', [
            'channel_name' => 'private-users.'.$this->owner->id,
            'socket_id' => '1234.5678',
        ])
        ->assertForbidden();
});

test('users channel: unauthenticated request is rejected', function () {
    $this->post('/broadcasting/auth', [
        'channel_name' => 'private-users.'.$this->owner->id,
        'socket_id' => '1234.5678',
    ])
        ->assertStatus(403);
});

// ── tasks.{taskUuid} channel (repository-owned) ───────────────────────────────

test('tasks channel via repository: owner receives 200', function () {
    $repository = Repository::factory()->create(['user_id' => $this->owner->id]);
    $task = Task::factory()->create(['repository_id' => $repository->id]);

    $this->actingAs($this->owner)
        ->post('/broadcasting/auth', [
            'channel_name' => 'private-tasks.'.$task->uuid,
            'socket_id' => '1234.5678',
        ])
        ->assertOk();
});

test('tasks channel via repository: non-owner receives 403', function () {
    $repository = Repository::factory()->create(['user_id' => $this->owner->id]);
    $task = Task::factory()->create(['repository_id' => $repository->id]);

    $this->actingAs($this->other)
        ->post('/broadcasting/auth', [
            'channel_name' => 'private-tasks.'.$task->uuid,
            'socket_id' => '1234.5678',
        ])
        ->assertForbidden();
});

// ── tasks.{taskUuid} channel (direct user_id) ─────────────────────────────────

test('tasks channel via direct user_id: owner receives 200', function () {
    $task = Task::factory()->generalChat($this->owner)->create();

    $this->actingAs($this->owner)
        ->post('/broadcasting/auth', [
            'channel_name' => 'private-tasks.'.$task->uuid,
            'socket_id' => '1234.5678',
        ])
        ->assertOk();
});

test('tasks channel via direct user_id: non-owner receives 403', function () {
    $task = Task::factory()->generalChat($this->owner)->create();

    $this->actingAs($this->other)
        ->post('/broadcasting/auth', [
            'channel_name' => 'private-tasks.'.$task->uuid,
            'socket_id' => '1234.5678',
        ])
        ->assertForbidden();
});

test('tasks channel: unknown uuid receives 403', function () {
    $this->actingAs($this->owner)
        ->post('/broadcasting/auth', [
            'channel_name' => 'private-tasks.'.fake()->uuid(),
            'socket_id' => '1234.5678',
        ])
        ->assertForbidden();
});

// ── broadcast event classes ───────────────────────────────────────────────────

test('all three broadcast event classes exist with correct channels', function () {
    $owner = User::factory()->create();
    $task = Task::factory()->generalChat($owner)->create();

    $recentChats = new \App\Events\RecentChatsUpdated($task);
    $taskStatus = new \App\Events\TaskStatusUpdated($task);
    $taskChat = new \App\Events\TaskChatUpdated($task);

    expect($recentChats->broadcastOn()->name)->toBe('private-users.'.$task->user_id)
        ->and($taskStatus->broadcastOn()->name)->toBe('private-tasks.'.$task->uuid)
        ->and($taskChat->broadcastOn()->name)->toBe('private-tasks.'.$task->uuid);
});
