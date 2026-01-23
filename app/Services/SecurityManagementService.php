<?php

namespace App\Services;

use App\Enums\MessageRole;
use App\Enums\MessageStatus;
use App\Enums\SecurityRunStatus;
use App\Enums\TaskStatus;
use App\Models\Message;
use App\Models\Repository;
use App\Models\SecurityRun;
use App\Models\Task;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class SecurityManagementService
{
    public function __construct(
        private SecurityAiResolver $aiResolver,
        private SecurityDecisionParser $parser,
    ) {}

    public function processRepository(Repository $repo): void
    {
        $task = $this->ensureSecurityTask($repo);

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

            if (! in_array($run->status, [SecurityRunStatus::Pending, SecurityRunStatus::WaitingCi], true)) {
                continue;
            }

            $status = $github->fetchCombinedStatus($repo->full_name, $pr['head']['sha']);
            $noChecks = ($status['total_count'] ?? 0) === 0 && ($status['check_runs_total_count'] ?? 0) === 0;
            $graceMinutes = (int) config('services.security_ai.no_checks_grace_minutes', 60);
            $pastGrace = $noChecks && $this->isPastNoChecksGrace($pr, $graceMinutes);

            $nextStatus = ($status['state'] === 'success' || $pastGrace)
                ? SecurityRunStatus::Researching
                : SecurityRunStatus::WaitingCi;

            $statusChanged = $run->status !== $nextStatus;

            $run->update([
                'status' => $nextStatus,
                'last_checked_at' => now(),
            ]);

            if (! $statusChanged) {
                continue;
            }

            if ($nextStatus === SecurityRunStatus::WaitingCi) {
                $this->postWaitingForCiMessage($task, $pr, $status);

                continue;
            }

            if ($nextStatus === SecurityRunStatus::Researching) {
                if ($pastGrace) {
                    $this->postNoChecksGraceMessage($task, $pr, $graceMinutes);
                }
                $this->dispatchOrchestratorPrompt($task, $repo, $pr, $status);
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

    private function postWaitingForCiMessage(Task $task, array $pr, array $status): void
    {
        $number = $pr['number'] ?? 'unknown';
        $title = $pr['title'] ?? 'Dependabot update';
        $url = $pr['html_url'] ?? null;
        $state = $status['state'] ?? 'unknown';

        $content = "Dependabot PR #{$number} ({$title}) is waiting for CI checks. Waiting for CI.\n";
        $content .= "CI status: {$state}.\n";
        if ($url) {
            $content .= "PR: {$url}\n";
        }

        Message::create([
            'task_id' => $task->id,
            'role' => MessageRole::Assistant,
            'status' => MessageStatus::Sent,
            'content' => $content,
        ]);
    }

    private function dispatchOrchestratorPrompt(Task $task, Repository $repo, array $pr, array $status): void
    {
        $content = $this->buildOrchestratorPrompt($repo, $pr, $status);

        if ($task->isRunning()) {
            Message::create([
                'task_id' => $task->id,
                'role' => MessageRole::User,
                'status' => MessageStatus::Queued,
                'content' => $content,
            ]);

            return;
        }

        $userMessage = Message::create([
            'task_id' => $task->id,
            'role' => MessageRole::User,
            'status' => MessageStatus::Sent,
            'content' => $content,
        ]);

        $task->dispatchMessage($userMessage);
    }

    private function buildOrchestratorPrompt(Repository $repo, array $pr, array $status): string
    {
        $promptPath = resource_path('prompts/security/dependabot.md');
        $template = File::exists($promptPath)
            ? File::get($promptPath)
            : 'You are the Security Management orchestrator.';

        $payload = [
            'repository' => [
                'id' => $repo->id,
                'full_name' => $repo->full_name,
                'default_branch' => $repo->default_branch,
                'private' => $repo->private,
                'description' => $repo->description,
            ],
            'pull_request' => [
                'id' => $pr['id'] ?? null,
                'number' => $pr['number'] ?? null,
                'title' => $pr['title'] ?? null,
                'url' => $pr['html_url'] ?? null,
                'base' => $pr['base']['ref'] ?? null,
                'head_sha' => $pr['head']['sha'] ?? null,
                'created_at' => $pr['created_at'] ?? null,
                'updated_at' => $pr['updated_at'] ?? null,
                'body' => $pr['body'] ?? null,
            ],
            'ci_status' => $status,
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return rtrim($template)."\n\n```json\n{$json}\n```";
    }

    private function isPastNoChecksGrace(array $pr, int $graceMinutes): bool
    {
        if ($graceMinutes <= 0) {
            return true;
        }

        $createdAt = $pr['created_at'] ?? null;
        if (! $createdAt) {
            return false;
        }

        return Carbon::parse($createdAt)->diffInMinutes(now()) >= $graceMinutes;
    }

    private function postNoChecksGraceMessage(Task $task, array $pr, int $graceMinutes): void
    {
        $number = $pr['number'] ?? 'unknown';
        $title = $pr['title'] ?? 'Dependabot update';

        $content = "Dependabot PR #{$number} ({$title}) has no CI checks after {$graceMinutes} minutes. Proceeding with security review.";

        Message::create([
            'task_id' => $task->id,
            'role' => MessageRole::Assistant,
            'status' => MessageStatus::Sent,
            'content' => $content,
        ]);
    }
}
