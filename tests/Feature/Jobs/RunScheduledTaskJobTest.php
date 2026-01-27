<?php

use App\Jobs\CloneRepositoryJob;
use App\Jobs\DeleteTaskJob;
use App\Jobs\RunScheduledPromptJob;
use App\Jobs\RunScheduledTaskJob;
use App\Models\Task;
use App\Models\TaskSchedule;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;

it('creates a task and chains clone + prompt for schedules', function () {
    Bus::fake();

    $schedule = TaskSchedule::factory()->create([
        'cron_expression' => '* * * * *',
        'prompt' => 'Run tests',
    ]);

    (new RunScheduledTaskJob($schedule->id))->handle();

    $task = Task::where('task_schedule_id', $schedule->id)->latest()->first();

    expect($task)->not->toBeNull();
    expect($task->workspace_path)->toContain('/home/ploi/workspaces/');

    Bus::assertChained([
        CloneRepositoryJob::class,
        RunScheduledPromptJob::class,
    ]);
});

it('schedules delete after completion when configured', function () {
    Queue::fake();

    $schedule = TaskSchedule::factory()->create(['delete_after_minutes' => 10]);
    $task = Task::factory()->create([
        'task_schedule_id' => $schedule->id,
        'repository_id' => $schedule->repository_id,
        'user_id' => $schedule->user_id,
    ]);

    $task->markAsCompleted();

    Queue::assertPushed(DeleteTaskJob::class);
});
