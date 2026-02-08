<?php

namespace App\Console\Commands;

use App\Services\TaskPullRequestPollingService;
use Illuminate\Console\Command;

class GitHubPollTaskPullRequestsCommand extends Command
{
    protected $signature = 'github:poll-task-prs';

    protected $description = 'Poll GitHub PRs associated with tasks and reawaken tasks when CI finishes';

    public function handle(TaskPullRequestPollingService $service): int
    {
        if (! config('services.github.pr_polling_enabled', true)) {
            $this->info('GitHub PR polling is disabled.');

            return self::SUCCESS;
        }

        $service->poll();

        return self::SUCCESS;
    }
}
