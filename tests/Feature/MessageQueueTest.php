<?php

use App\Enums\MessageStatus;
use App\Models\Message;
use App\Models\Task;

test('message defaults to sent status', function () {
    $task = Task::factory()->create();
    $message = Message::factory()->user()->create(['task_id' => $task->id]);

    expect($message->status)->toBe(MessageStatus::Sent);
    expect($message->isSent())->toBeTrue();
    expect($message->isQueued())->toBeFalse();
});

test('message can be created with queued status', function () {
    $task = Task::factory()->create();
    $message = Message::factory()->user()->queued()->create(['task_id' => $task->id]);

    expect($message->status)->toBe(MessageStatus::Queued);
    expect($message->isQueued())->toBeTrue();
    expect($message->isSent())->toBeFalse();
});

test('queued message can be marked as sent', function () {
    $task = Task::factory()->create();
    $message = Message::factory()->user()->queued()->create(['task_id' => $task->id]);

    expect($message->isQueued())->toBeTrue();

    $message->markAsSent();

    expect($message->fresh()->isSent())->toBeTrue();
});

test('task can have multiple queued messages', function () {
    $task = Task::factory()->create();

    Message::factory()->user()->queued()->create([
        'task_id' => $task->id,
        'content' => 'First queued message',
    ]);

    Message::factory()->user()->queued()->create([
        'task_id' => $task->id,
        'content' => 'Second queued message',
    ]);

    Message::factory()->user()->sent()->create([
        'task_id' => $task->id,
        'content' => 'Regular sent message',
    ]);

    $queuedMessages = $task->messages()
        ->where('status', MessageStatus::Queued)
        ->get();

    $sentMessages = $task->messages()
        ->where('status', MessageStatus::Sent)
        ->get();

    expect($queuedMessages)->toHaveCount(2);
    expect($sentMessages)->toHaveCount(1);
});

test('queued messages are ordered oldest first', function () {
    $task = Task::factory()->create();

    $first = Message::factory()->user()->queued()->create([
        'task_id' => $task->id,
        'content' => 'First',
        'created_at' => now()->subMinutes(2),
    ]);

    $second = Message::factory()->user()->queued()->create([
        'task_id' => $task->id,
        'content' => 'Second',
        'created_at' => now()->subMinute(),
    ]);

    $third = Message::factory()->user()->queued()->create([
        'task_id' => $task->id,
        'content' => 'Third',
        'created_at' => now(),
    ]);

    $queuedMessages = $task->messages()
        ->where('status', MessageStatus::Queued)
        ->oldest()
        ->get();

    expect($queuedMessages[0]->id)->toBe($first->id);
    expect($queuedMessages[1]->id)->toBe($second->id);
    expect($queuedMessages[2]->id)->toBe($third->id);
});
