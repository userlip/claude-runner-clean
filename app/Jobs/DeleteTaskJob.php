<?php

namespace App\Jobs;

use App\Models\Task;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;

class DeleteTaskJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(public int $taskId) {}

    public function handle(): void
    {
        $task = Task::find($this->taskId);

        if ($task) {
            $task->delete();
        }
    }
}
