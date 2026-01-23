<?php

namespace App\Services;

use App\Enums\SecurityRunStatus;
use App\Models\Repository;
use App\Models\SecurityRun;

class SecurityManagementService
{
    public function __construct(
        private SecurityAiResolver $aiResolver,
        private SecurityDecisionParser $parser,
    ) {}

    public function processRepository(Repository $repo): void
    {
        $connection = $repo->user?->githubConnection;
        if (! $connection) {
            return;
        }

        $github = new GitHubService($connection);
        $prs = $github->fetchDependabotPullRequests($repo->full_name);

        foreach ($prs as $pr) {
            $run = SecurityRun::firstOrCreate(
                ['repository_id' => $repo->id, 'github_pr_id' => $pr['id']],
                [
                    'github_pr_number' => $pr['number'],
                    'status' => SecurityRunStatus::Pending,
                ]
            );

            if ($run->status === SecurityRunStatus::Pending) {
                $status = $github->fetchCombinedStatus($repo->full_name, $pr['head']['sha']);
                $run->update([
                    'status' => $status['state'] === 'success'
                        ? SecurityRunStatus::Researching
                        : SecurityRunStatus::WaitingCi,
                    'last_checked_at' => now(),
                ]);
            }
        }
    }
}
