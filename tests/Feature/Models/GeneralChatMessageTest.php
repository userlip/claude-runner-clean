<?php

use App\Enums\MessageRole;
use App\Models\GeneralChat;
use App\Models\GeneralChatMessage;

test('message belongs to general chat', function () {
    $chat = GeneralChat::factory()->create();
    $message = GeneralChatMessage::factory()->for($chat)->create();

    expect($message->generalChat)->toBeInstanceOf(GeneralChat::class);
    expect($message->generalChat->id)->toBe($chat->id);
});

test('message role is cast to enum', function () {
    $message = GeneralChatMessage::factory()->create();

    expect($message->role)->toBeInstanceOf(MessageRole::class);
});

test('isFromUser returns true for user messages', function () {
    $userMessage = GeneralChatMessage::factory()->user()->create();
    $assistantMessage = GeneralChatMessage::factory()->assistant()->create();

    expect($userMessage->isFromUser())->toBeTrue();
    expect($assistantMessage->isFromUser())->toBeFalse();
});

test('isFromAssistant returns true for assistant messages', function () {
    $userMessage = GeneralChatMessage::factory()->user()->create();
    $assistantMessage = GeneralChatMessage::factory()->assistant()->create();

    expect($assistantMessage->isFromAssistant())->toBeTrue();
    expect($userMessage->isFromAssistant())->toBeFalse();
});

test('can append raw output', function () {
    $message = GeneralChatMessage::factory()->assistant()->create(['raw_output' => 'Hello']);

    $message->appendRawOutput(' World');

    expect($message->fresh()->raw_output)->toBe('Hello World');
});

test('can append raw output when initially null', function () {
    $message = GeneralChatMessage::factory()->assistant()->create(['raw_output' => null]);

    $message->appendRawOutput('{"type":"text"}');
    $message->appendRawOutput("\n");
    $message->appendRawOutput('{"type":"result"}');

    expect($message->raw_output)->toBe("{\"type\":\"text\"}\n{\"type\":\"result\"}");
});

test('can add tool calls', function () {
    $message = GeneralChatMessage::factory()->assistant()->create(['tool_calls' => null]);

    $message->addToolCall(['name' => 'Read', 'input' => ['file_path' => '/test']]);
    $message->addToolCall(['name' => 'Edit', 'input' => ['file_path' => '/test']]);

    expect($message->fresh()->tool_calls)->toHaveCount(2);
    expect($message->fresh()->tool_calls[0]['name'])->toBe('Read');
});
