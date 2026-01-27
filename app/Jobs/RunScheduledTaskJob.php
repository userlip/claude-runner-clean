<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;

class RunScheduledTaskJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(public int $scheduleId) {}

    public function handle(): void {}
}
