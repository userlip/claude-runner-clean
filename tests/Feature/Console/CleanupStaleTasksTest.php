<?php

use App\Enums\TaskStatus;
use App\Models\Message;
use App\Models\Task;
use Illuminate\Support\Facades\DB;

test('cleanup stale tasks skips running tasks with recent streamed message activity', function () {
    $task = Task::factory()->running()->create();

    $message = Message::factory()->assistant()->create([
        'task_id' => $task->id,
    ]);

    DB::table('messages')
        ->where('id', $message->id)
        ->update([
            'created_at' => now()->subMinutes(25),
            'updated_at' => now()->subMinutes(5),
        ]);

    $task->update(['last_message_at' => now()->subMinutes(25)]);

    $this->artisan('tasks:cleanup-stale', ['--minutes' => 20])->assertSuccessful();

    $task->refresh();

    expect($task->status)->toBe(TaskStatus::Running)
        ->and($task->completed_at)->toBeNull();
});

test('cleanup stale tasks completes truly inactive running tasks', function () {
    $task = Task::factory()->running()->create();

    $message = Message::factory()->assistant()->create([
        'task_id' => $task->id,
    ]);

    DB::table('messages')
        ->where('id', $message->id)
        ->update([
            'created_at' => now()->subMinutes(25),
            'updated_at' => now()->subMinutes(25),
        ]);

    $task->update(['last_message_at' => now()->subMinutes(25)]);

    $this->artisan('tasks:cleanup-stale', ['--minutes' => 20])->assertSuccessful();

    $task->refresh();

    expect($task->status)->toBe(TaskStatus::Completed)
        ->and($task->completed_at)->not->toBeNull();
});
