<?php

use App\Enums\MessageRole;
use App\Models\Message;
use App\Models\Task;

test('message belongs to task', function () {
    $message = Message::factory()->create();

    expect($message->task)->toBeInstanceOf(Task::class);
});

test('task has many messages', function () {
    $task = Task::factory()->create();
    Message::factory()->count(5)->create(['task_id' => $task->id]);

    expect($task->messages)->toHaveCount(5);
});

test('message role is cast to enum', function () {
    $message = Message::factory()->create();

    expect($message->role)->toBeInstanceOf(MessageRole::class);
});

test('isFromUser returns true for user messages', function () {
    $userMessage = Message::factory()->user()->create();
    $assistantMessage = Message::factory()->assistant()->create();

    expect($userMessage->isFromUser())->toBeTrue();
    expect($assistantMessage->isFromUser())->toBeFalse();
});

test('can append raw output', function () {
    $message = Message::factory()->assistant()->create(['raw_output' => null]);

    $message->appendRawOutput('{"type":"text"}');
    $message->appendRawOutput("\n");
    $message->appendRawOutput('{"type":"result"}');

    expect($message->raw_output)->toBe("{\"type\":\"text\"}\n{\"type\":\"result\"}");
});

test('can add tool calls', function () {
    $message = Message::factory()->assistant()->create(['tool_calls' => null]);

    $message->addToolCall(['name' => 'Read', 'params' => ['file' => 'test.php']]);
    $message->addToolCall(['name' => 'Write', 'params' => ['file' => 'out.php']]);

    expect($message->tool_calls)->toHaveCount(2);
    expect($message->tool_calls[0]['name'])->toBe('Read');
});
