<?php

use App\Jobs\RunScheduledTaskJob;
use App\Models\TaskSchedule;
use Illuminate\Support\Facades\Queue;

it('dispatches a job for due schedules', function () {
    Queue::fake();

    $schedule = TaskSchedule::factory()->create([
        'cron_expression' => '* * * * *',
        'last_run_at' => null,
        'is_active' => true,
    ]);

    $this->artisan('tasks:run-schedules')->assertSuccessful();

    Queue::assertPushed(RunScheduledTaskJob::class, fn ($job) => $job->scheduleId === $schedule->id);
});
