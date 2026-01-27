<?php

namespace App\Jobs;

use App\Enums\MessageRole;
use App\Enums\MessageStatus;
use App\Models\Message;
use App\Models\Task;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;

class RunScheduledPromptJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(public int $taskId) {}

    public function handle(): void
    {
        $task = Task::findOrFail($this->taskId);
        $schedule = $task->taskSchedule;

        if (! $schedule) {
            return;
        }

        $message = Message::create([
            'task_id' => $task->id,
            'role' => MessageRole::User,
            'status' => MessageStatus::Sent,
            'content' => $schedule->prompt,
        ]);

        $task->dispatchMessage($message, continue: false);
    }
}
