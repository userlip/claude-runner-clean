<?php

namespace App\Jobs;

use App\Models\Task;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CheckSubagentStatusJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public function __construct(
        public Task $task,
        public int $attempt = 1,
        public int $maxAttempts = 120
    ) {}

    public function handle(): void
    {
        // Disabled: this job is no longer dispatched. The handle method is a no-op
        // so that any previously-queued serialized jobs safely do nothing.
    }
}
