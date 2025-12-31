<?php

namespace App\Console\Commands;

use App\Models\Proposal;
use App\Services\ProposalExecutionService;
use Illuminate\Console\Command;

class ProposalExecuteCommand extends Command
{
    protected $signature = 'proposal:execute
                            {id : The proposal ID or UUID to execute}
                            {--force : Execute even if already approved}';

    protected $description = 'Manually execute an approved proposal';

    public function handle(ProposalExecutionService $service): int
    {
        $identifier = $this->argument('id');

        $proposal = Proposal::where('id', $identifier)
            ->orWhere('uuid', $identifier)
            ->first();

        if (! $proposal) {
            $this->error("Proposal not found: {$identifier}");

            return self::FAILURE;
        }

        if ($proposal->status !== \App\Enums\ProposalStatus::Approved && ! $this->option('force')) {
            $this->error('Proposal is not approved. Use --force to execute anyway.');

            return self::FAILURE;
        }

        if ($proposal->executed_task_id && ! $this->option('force')) {
            $this->error('Proposal already has an executed task. Use --force to create a new one.');

            return self::FAILURE;
        }

        $this->info("Executing proposal: {$proposal->title}");

        $task = $service->execute($proposal);

        $this->info("Task created: #{$task->id}");
        $this->info("Workspace: {$task->workspace_path}");

        return self::SUCCESS;
    }
}
