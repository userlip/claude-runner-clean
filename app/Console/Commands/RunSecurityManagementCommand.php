<?php

namespace App\Console\Commands;

use App\Jobs\RunSecurityManagementJob;
use Illuminate\Console\Command;

class RunSecurityManagementCommand extends Command
{
    protected $signature = 'security:orchestrate {--repo= : Repository ID to run}';

    protected $description = 'Run security management orchestration.';

    public function handle(): int
    {
        RunSecurityManagementJob::dispatch($this->option('repo'));

        return self::SUCCESS;
    }
}
