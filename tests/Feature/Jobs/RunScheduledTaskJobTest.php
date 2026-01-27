<?php

use App\Jobs\CloneRepositoryJob;
use App\Jobs\RunScheduledPromptJob;
use App\Jobs\RunScheduledTaskJob;
use App\Models\Task;
use App\Models\TaskSchedule;
use Illuminate\Support\Facades\Bus;

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
