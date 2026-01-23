<?php

namespace App\Services;

use App\Enums\SecurityRunStatus;
use App\Enums\TaskStatus;
use App\Models\Repository;
use App\Models\SecurityRun;
use App\Models\Task;
use Illuminate\Support\Facades\Process;

class SecurityManagementService
{
    public function __construct(
        private SecurityAiResolver $aiResolver,
        private SecurityDecisionParser $parser,
    ) {}

    public function processRepository(Repository $repo): void
    {
        $this->ensureSecurityTask($repo);

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

    public function ensureSecurityTask(Repository $repo): Task
    {
        if ($repo->securityTask) {
            return $repo->securityTask;
        }

        $task = Task::create([
            'title' => "Security Management: {$repo->name}",
            'repository_id' => $repo->id,
            'ai_provider_id' => $this->aiResolver->orchestratorProvider()?->id,
            'status' => TaskStatus::Pending,
        ]);

        $repo->update(['security_task_id' => $task->id]);

        return $task;
    }

    private function deployRepository(Repository $repo): void
    {
        $ploi = new PloiService;

        if (! $repo->ploi_site_id) {
            $ploi->resolveSiteIdForRepository($repo, $repo->name.'.marin.sh');
        }

        if ($repo->ploi_site_id) {
            Process::run([
                'ploi', 'deploy',
                '--server='.$repo->ploi_server_id,
                '--site='.$repo->ploi_site_id,
                '--no-interaction',
            ]);
        }
    }
}
