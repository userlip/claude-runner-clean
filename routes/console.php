<?php

use App\Enums\ResearchModule;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Research Pipeline - Continuous research every 2 hours, staggered by module
// Offset minutes are converted to cron-compatible minute values (0-59)
// Modules with offset >= 60 run at the odd hours (1, 3, 5, etc.)
foreach (ResearchModule::cases() as $module) {
    $offsetMinutes = $module->scheduledOffsetMinutes();
    $minutes = $offsetMinutes % 60;
    $hourOffset = intdiv($offsetMinutes, 60);

    // For even hour offset (0), use */2 starting at 0 (0, 2, 4, 6...)
    // For odd hour offset (1), use 1-23/2 (1, 3, 5, 7...)
    $hours = $hourOffset === 0 ? '*/2' : '1-23/2';

    Schedule::command("research:run {$module->value}")
        ->cron("{$minutes} {$hours} * * *")
        ->withoutOverlapping()
        ->runInBackground()
        ->appendOutputTo(storage_path('logs/research.log'));
}

Schedule::command('security:orchestrate')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

// API Health Checks - Run every 2 hours to monitor Scrappa API endpoints
// Schedule::command('scrappa:health-check')
//     ->everyTwoHours()
//     ->withoutOverlapping()
//     ->runInBackground()
//     ->appendOutputTo(storage_path('logs/api-health-check.log'));
