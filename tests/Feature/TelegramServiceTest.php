<?php

use App\Models\Message;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('message observer skips telegram delivery when telegram is not configured', function () {
    config([
        'telegram.bots.claude_runner.token' => null,
        'telegram.admin_chat_id' => null,
    ]);

    $task = Task::factory()->create();

    $message = Message::factory()->for($task)->create();

    expect($message->fresh()->telegram_message_id)->toBeNull();
});
