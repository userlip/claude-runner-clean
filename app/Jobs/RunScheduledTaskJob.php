<?php

namespace App\Jobs;

use App\Models\Task;
use App\Models\TaskSchedule;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;

class RunScheduledTaskJob implements ShouldQueue
{
    use Batchable, Dispatchable, Queueable;

    public function __construct(public int $scheduleId) {}

    public function handle(): void
    {
        $schedule = TaskSchedule::findOrFail($this->scheduleId);

        $workspacePath = '/home/ploi/workspaces/'.Str::slug($schedule->repository->name).'-'.Str::random(8);

        $task = Task::create([
            'user_id' => $schedule->user_id,
            'repository_id' => $schedule->repository_id,
            'task_schedule_id' => $schedule->id,
            'ai_provider_id' => $schedule->ai_provider_id,
            'workspace_path' => $workspacePath,
            'title' => 'Scheduled: '.$schedule->name,
        ]);

        $schedule->update(['last_task_id' => $task->id]);

        Bus::chain([
            new CloneRepositoryJob($task),
            new RunScheduledPromptJob($task->id),
        ])->dispatch();
    }
}
