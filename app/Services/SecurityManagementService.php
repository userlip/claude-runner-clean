<?php

namespace App\Services;

use App\Enums\MajorUpgradeStatus;
use App\Enums\MessageRole;
use App\Enums\MessageStatus;
use App\Enums\ProposalPriority;
use App\Enums\ProposalStatus;
use App\Enums\ProposalType;
use App\Enums\SecurityRunStatus;
use App\Enums\TaskStatus;
use App\Models\MajorUpgradeRun;
use App\Models\Message;
use App\Models\Proposal;
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

        $this->processResearchingRuns($repo, $task, $github);
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

        if (! $repo->ploi_site_id) {
            return;
        }

        $command = [
            'ploi', 'deploy',
            '--server='.$repo->ploi_server_id,
            '--site='.$repo->ploi_site_id,
            '--no-interaction',
        ];

        $result = Process::run($command);

        if ($result->successful()) {
            return;
        }

        if ($this->isUntrackedMergeError($result->errorOutput())) {
            $updated = $ploi->ensureDeployScriptCleanup(
                (string) $repo->ploi_server_id,
                (string) $repo->ploi_site_id,
                'rm -rf public/build'
            );

            if ($updated) {
                $result = Process::run($command);
            }
        }

        if (! $result->successful()) {
            throw new \RuntimeException('Deploy failed: '.$result->errorOutput());
        }
    }

    private function isUntrackedMergeError(string $errorOutput): bool
    {
        $message = strtolower($errorOutput);

        return str_contains($message, 'untracked working tree files would be overwritten by merge');
    }

    private function processResearchingRuns(Repository $repo, Task $task, GitHubService $github): void
    {
        $runs = SecurityRun::query()
            ->where('repository_id', $repo->id)
            ->where('status', SecurityRunStatus::Researching->value)
            ->get();

        foreach ($runs as $run) {
            if ($run->decision_summary) {
                continue;
            }

            $decisionContext = $this->findDecisionContextForRun($task, $run);
            if (! $decisionContext) {
                continue;
            }

            $decision = $decisionContext['decision'];
            $payload = $decisionContext['payload'];

            $run->update([
                'decision_summary' => json_encode($decision, JSON_UNESCAPED_SLASHES),
            ]);

            $updateDetails = $this->determineUpdateDetails($payload, $repo, $run, $github);

            if ($updateDetails['update_type'] === 'major') {
                $this->createMajorUpgradeRun(
                    $repo,
                    $run->github_pr_number,
                    $payload['pull_request'] ?? $payload
                );
                $this->createUserNeededAction($task, $repo, $run, $decision, $updateDetails);
                $run->update(['status' => SecurityRunStatus::NeedsUserAction]);

                continue;
            }

            $mergeAllowed = filter_var($decision['merge_allowed'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $this->postDecisionMessage($task, $run, $decision, $mergeAllowed);

            if (! $mergeAllowed) {
                $run->update(['status' => SecurityRunStatus::Failed]);

                continue;
            }

            $run->update(['status' => SecurityRunStatus::Approved]);

            try {
                $mergeResult = $github->mergePullRequest($repo->full_name, $run->github_pr_number);
                $run->update([
                    'status' => SecurityRunStatus::Merged,
                    'merge_commit_sha' => $mergeResult['sha'] ?? $mergeResult['merge_commit_sha'] ?? null,
                ]);

                $this->postMergedMessage($task, $run, $mergeResult);

                $this->deployRepository($repo);
                $run->update(['status' => SecurityRunStatus::Deployed]);

                $this->postDeployedMessage($task, $run);
            } catch (\Throwable $e) {
                $run->update([
                    'status' => SecurityRunStatus::Failed,
                    'error_message' => $e->getMessage(),
                ]);

                $this->postFailedMessage($task, $run, $e->getMessage());
            }
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    /**
     * @return array{decision: array<string, mixed>, payload: array<string, mixed>}|null
     */
    private function findDecisionContextForRun(Task $task, SecurityRun $run): ?array
    {
        $userMessages = Message::query()
            ->where('task_id', $task->id)
            ->where('role', MessageRole::User)
            ->orderByDesc('id')
            ->get();

        foreach ($userMessages as $userMessage) {
            $payload = $this->extractJsonBlock($userMessage->content ?? '');
            if (! $payload) {
                continue;
            }

            $payloadNumber = $this->extractPrNumber($payload);
            if (! $payloadNumber || $payloadNumber !== $run->github_pr_number) {
                continue;
            }

            $assistantMessage = Message::query()
                ->where('task_id', $task->id)
                ->where('role', MessageRole::Assistant)
                ->where('id', '>', $userMessage->id)
                ->orderBy('id')
                ->first();

            if (! $assistantMessage) {
                return null;
            }

            $decision = $this->parser->parse($assistantMessage->content ?? '');
            if (! $decision) {
                return null;
            }

            return [
                'decision' => $decision,
                'payload' => $payload,
            ];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractPrNumber(array $payload): ?int
    {
        if (isset($payload['pr_number'])) {
            return (int) $payload['pr_number'];
        }

        $pullRequest = $payload['pull_request'] ?? null;
        if (is_array($pullRequest) && isset($pullRequest['number'])) {
            return (int) $pullRequest['number'];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{update_type: string, dependency: string|null, from_version: string|null, to_version: string|null}
     */
    private function determineUpdateDetails(array $payload, Repository $repo, SecurityRun $run, GitHubService $github): array
    {
        $title = $payload['pull_request']['title'] ?? null;
        $body = $payload['pull_request']['body'] ?? null;

        if (! $title) {
            $pr = $github->fetchPullRequest($repo->full_name, $run->github_pr_number);
            $title = $pr['title'] ?? null;
            $body = $pr['body'] ?? null;
        }

        $dependency = $this->extractDependencyName($title ?? '');
        [$from, $to] = $this->extractVersionPair($title ?? '', $body ?? '');

        return [
            'update_type' => $this->classifyUpdateType($from, $to),
            'dependency' => $dependency,
            'from_version' => $from,
            'to_version' => $to,
        ];
    }

    private function extractDependencyName(string $title): ?string
    {
        if (preg_match('/bump\\s+([^\\s]+)\\s+from/i', $title, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function extractVersionPair(string $title, string $body): array
    {
        if (preg_match('/from\\s+([0-9][^\\s]*)\\s+to\\s+([0-9][^\\s]*)/i', $title, $matches)) {
            return [$this->normalizeVersion($matches[1]), $this->normalizeVersion($matches[2])];
        }

        if (preg_match('/from\\s+([0-9][^\\s]*)\\s+to\\s+([0-9][^\\s]*)/i', $body, $matches)) {
            return [$this->normalizeVersion($matches[1]), $this->normalizeVersion($matches[2])];
        }

        return [null, null];
    }

    private function normalizeVersion(string $version): string
    {
        $normalized = ltrim($version, 'vV');

        return preg_replace('/[^0-9.]/', '', $normalized) ?? $normalized;
    }

    private function classifyUpdateType(?string $from, ?string $to): string
    {
        if (! $from || ! $to) {
            return 'unknown';
        }

        [$fromMajor, $fromMinor, $fromPatch] = $this->parseSemver($from);
        [$toMajor, $toMinor, $toPatch] = $this->parseSemver($to);

        if ($fromMajor === null || $toMajor === null) {
            return 'unknown';
        }

        if ($toMajor > $fromMajor) {
            return 'major';
        }

        if ($toMinor !== null && $fromMinor !== null && $toMinor > $fromMinor) {
            return 'minor';
        }

        if ($toPatch !== null && $fromPatch !== null && $toPatch > $fromPatch) {
            return 'patch';
        }

        return 'unknown';
    }

    /**
     * @return array{0: int|null, 1: int|null, 2: int|null}
     */
    private function parseSemver(string $version): array
    {
        if (preg_match('/(\\d+)\\.(\\d+)\\.(\\d+)/', $version, $matches)) {
            return [(int) $matches[1], (int) $matches[2], (int) $matches[3]];
        }

        if (preg_match('/(\\d+)\\.(\\d+)/', $version, $matches)) {
            return [(int) $matches[1], (int) $matches[2], null];
        }

        if (preg_match('/(\\d+)/', $version, $matches)) {
            return [(int) $matches[1], null, null];
        }

        return [null, null, null];
    }

    /**
     * @param  array<string, mixed>  $decision
     * @param  array<string, mixed>  $updateDetails
     */
    private function createUserNeededAction(Task $task, Repository $repo, SecurityRun $run, array $decision, array $updateDetails): void
    {
        $existing = Proposal::query()
            ->where('project', 'User needed actions')
            ->where('status', ProposalStatus::Pending)
            ->where('proposed_action->repository_id', $repo->id)
            ->where('proposed_action->pr_number', $run->github_pr_number)
            ->exists();

        if ($existing) {
            return;
        }

        $dependency = $updateDetails['dependency'] ?? 'dependency';
        $from = $updateDetails['from_version'] ?? 'current';
        $to = $updateDetails['to_version'] ?? 'target';
        $breaking = $decision['breaking_changes'] ?? $decision['rationale'] ?? 'Review required.';

        Proposal::create([
            'title' => "User action needed: major dependency update (PR #{$run->github_pr_number})",
            'description' => "Major update detected for {$dependency} ({$from} → {$to}).\n\nBreaking changes research:\n{$breaking}\n\nChoose an action: merge, ignore major, or keep open.",
            'priority' => ProposalPriority::Medium,
            'status' => ProposalStatus::Pending,
            'project' => 'User needed actions',
            'type' => ProposalType::Other,
            'task_id' => $task->id,
            'proposed_action' => [
                'repository_id' => $repo->id,
                'pr_number' => $run->github_pr_number,
                'dependency' => $dependency,
                'from_version' => $from,
                'to_version' => $to,
                'options' => ['merge', 'ignore_major', 'leave_open'],
            ],
        ]);

        Message::create([
            'task_id' => $task->id,
            'role' => MessageRole::Assistant,
            'status' => MessageStatus::Sent,
            'content' => "User action required for PR #{$run->github_pr_number}. See \"User needed actions\" for options.",
        ]);
    }

    private function createMajorUpgradeRun(Repository $repo, int $prNumber, array $prPayload): MajorUpgradeRun
    {
        $existing = MajorUpgradeRun::query()
            ->where('repository_id', $repo->id)
            ->where('github_pr_number', $prNumber)
            ->first();

        if ($existing) {
            return $existing;
        }

        $task = Task::create([
            'title' => "Major Upgrade: {$repo->name} PR #{$prNumber}",
            'repository_id' => $repo->id,
            'ai_provider_id' => $this->aiResolver->orchestratorProvider()?->id,
            'status' => TaskStatus::Pending,
        ]);

        return MajorUpgradeRun::create([
            'repository_id' => $repo->id,
            'github_pr_number' => $prNumber,
            'status' => MajorUpgradeStatus::Pending,
            'source_pr_url' => $prPayload['html_url'] ?? null,
            'source_pr_sha' => $prPayload['head']['sha'] ?? null,
            'created_by_task_id' => $task->id,
        ]);
    }

    public function createMajorUpgradeRunForTest(Repository $repo, int $prNumber): MajorUpgradeRun
    {
        return $this->createMajorUpgradeRun($repo, $prNumber, []);
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

    /**
     * @return array<string, mixed>|null
     */
    private function extractJsonBlock(string $content): ?array
    {
        if (! preg_match_all('/```json\n(.*?)\n```/s', $content, $matches)) {
            return null;
        }
        foreach ($matches[1] as $block) {
            $data = json_decode($block, true);
            if (is_array($data)) {
                return $data;
            }
        }

        return null;
    }

    private function buildOrchestratorPrompt(Repository $repo, array $pr, array $status): string
    {
        $promptPath = resource_path('prompts/security/dependabot.md');
        $template = File::exists($promptPath)
            ? File::get($promptPath)
            : 'You are the Security Management orchestrator.';

        $payload = [
            'repo' => $repo->full_name,
            'pr_number' => $pr['number'] ?? null,
            'head_sha' => $pr['head']['sha'] ?? null,
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

    /**
     * @param  array<string, mixed>  $decision
     */
    private function postDecisionMessage(Task $task, SecurityRun $run, array $decision, bool $mergeAllowed): void
    {
        $risk = $decision['risk_level'] ?? 'unknown';
        $rationale = $decision['rationale'] ?? 'No rationale provided.';
        $allowedText = $mergeAllowed ? 'approved' : 'blocked';

        $content = "Decision for PR #{$run->github_pr_number}: {$allowedText}. Risk level: {$risk}.";
        $content .= "\nRationale: {$rationale}";

        Message::create([
            'task_id' => $task->id,
            'role' => MessageRole::Assistant,
            'status' => MessageStatus::Sent,
            'content' => $content,
        ]);
    }

    /**
     * @param  array<string, mixed>  $mergeResult
     */
    private function postMergedMessage(Task $task, SecurityRun $run, array $mergeResult): void
    {
        $sha = $mergeResult['sha'] ?? $mergeResult['merge_commit_sha'] ?? 'unknown';

        Message::create([
            'task_id' => $task->id,
            'role' => MessageRole::Assistant,
            'status' => MessageStatus::Sent,
            'content' => "Merged PR #{$run->github_pr_number}. Merge commit: {$sha}.",
        ]);
    }

    private function postDeployedMessage(Task $task, SecurityRun $run): void
    {
        Message::create([
            'task_id' => $task->id,
            'role' => MessageRole::Assistant,
            'status' => MessageStatus::Sent,
            'content' => "Deployed changes for PR #{$run->github_pr_number}.",
        ]);
    }

    private function postFailedMessage(Task $task, SecurityRun $run, string $message): void
    {
        Message::create([
            'task_id' => $task->id,
            'role' => MessageRole::Assistant,
            'status' => MessageStatus::Sent,
            'content' => "Security run failed for PR #{$run->github_pr_number}: {$message}",
        ]);
    }
}
