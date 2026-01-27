<?php

namespace App\Console\Commands;

use App\Jobs\RunScheduledTaskJob;
use App\Models\TaskSchedule;
use Cron\CronExpression;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RunTaskSchedulesCommand extends Command
{
    protected $signature = 'tasks:run-schedules';

    protected $description = 'Dispatch scheduled tasks that are due';

    public function handle(): int
    {
        $now = now();

        TaskSchedule::query()
            ->where('is_active', true)
            ->get()
            ->each(function (TaskSchedule $schedule) use ($now) {
                $expression = new CronExpression($schedule->cron_expression);

                if (! $expression->isDue($now->toDateTimeString())) {
                    return;
                }

                $alreadyRanThisMinute = $schedule->last_run_at
                    && $schedule->last_run_at->format('Y-m-d H:i') === $now->format('Y-m-d H:i');

                if ($alreadyRanThisMinute) {
                    return;
                }

                DB::transaction(function () use ($schedule, $now) {
                    $schedule->refresh();

                    $alreadyRan = $schedule->last_run_at
                        && $schedule->last_run_at->format('Y-m-d H:i') === $now->format('Y-m-d H:i');

                    if ($alreadyRan) {
                        return;
                    }

                    $schedule->update(['last_run_at' => $now]);

                    RunScheduledTaskJob::dispatch($schedule->id);
                });
            });

        return self::SUCCESS;
    }
}
