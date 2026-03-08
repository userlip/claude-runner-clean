<?php

use App\Models\Message;
use App\Models\Task;
use App\Services\TelegramService;

test('task messages are not mirrored to Telegram', function () {
    $telegramService = Mockery::mock(TelegramService::class);
    $telegramService->shouldReceive('sendTaskMessage')->never();
    $telegramService->shouldReceive('updateTaskMessage')->never();
    app()->instance(TelegramService::class, $telegramService);

    $task = Task::factory()->create();

    $message = Message::factory()->create([
        'task_id' => $task->id,
        'content' => 'This should stay in the app.',
    ]);

    expect($message->telegram_message_id)->toBeNull();
});
