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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

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

        // Check if GitHub API rate limit is available before proceeding
        if (! $github->hasRateLimitRemaining()) {
            Log::info("Skipping security processing for {$repo->name}: GitHub API rate limit exhausted");

            return;
        }

        $prs = $github->fetchDependabotPullRequests($repo->full_name);

        foreach ($prs as $pr) {
            $run = SecurityRun::firstOrCreate(
                ['repository_id' => $repo->id, 'github_pr_id' => $pr['id']],
                [
                    'github_pr_number' => $pr['number'],
                    'pr_title' => $pr['title'] ?? null,
                    'status' => SecurityRunStatus::Pending,
                ]
            );

            if (! $run->pr_title && isset($pr['title'])) {
                $run->update(['pr_title' => $pr['title']]);
            }

            if (! in_array($run->status, [SecurityRunStatus::Pending, SecurityRunStatus::WaitingCi], true)) {
                continue;
            }

            $status = $github->fetchCombinedStatus($repo->full_name, $pr['head']['sha']);
            $noChecks = ($status['total_count'] ?? 0) === 0 && ($status['check_runs_total_count'] ?? 0) === 0;
            $graceMinutes = (int) config('services.security_ai.no_checks_grace_minutes', 60);
            $pastGrace = $noChecks && $this->isPastNoChecksGrace($pr, $graceMinutes);
            $ciFailure = $status['state'] === 'failure' || $status['state'] === 'error';

            $nextStatus = match (true) {
                $status['state'] === 'success' || $pastGrace => SecurityRunStatus::Researching,
                $ciFailure => SecurityRunStatus::FixingCi,
                default => SecurityRunStatus::WaitingCi,
            };

            $statusChanged = $run->status !== $nextStatus;

            // For WaitingCi, update status immediately (no task needed yet)
            if ($nextStatus === SecurityRunStatus::WaitingCi) {
                $run->update([
                    'status' => $nextStatus,
                    'last_checked_at' => now(),
                ]);

                continue;
            }

            // For Researching or FixingCi, use atomic lock to prevent race conditions
            $lockKey = 'security_dispatch_lock';
            $lock = Cache::lock($lockKey, 10);

            if (! $lock->get()) {
                $run->update(['last_checked_at' => now()]);

                continue;
            }

            try {
                if (! $this->canDispatchSecurityTask()) {
                    $run->update(['last_checked_at' => now()]);

                    continue;
                }

                $run->update([
                    'status' => $nextStatus,
                    'last_checked_at' => now(),
                ]);
            } finally {
                $lock->release();
            }

            if (! $statusChanged) {
                continue;
            }

            // Create or get task for this specific PR
            $task = $this->ensureTaskForRun($run, $repo);

            if ($nextStatus === SecurityRunStatus::FixingCi) {
                if ($pastGrace) {
                    $this->postNoChecksGraceMessage($task, $pr, $graceMinutes);
                }
                $this->dispatchCiFixerPrompt($task, $run, $repo, $pr, $status);

                continue;
            }

            if ($nextStatus === SecurityRunStatus::Researching) {
                if ($pastGrace) {
                    $this->postNoChecksGraceMessage($task, $pr, $graceMinutes);
                }
                $this->dispatchOrchestratorPrompt($task, $run, $repo, $pr, $status);
            }
        }

        $this->processFixingCiRuns($repo, $github);
        $this->processResearchingRuns($repo, $github);
        $this->syncClosedPrs($repo, $github);
        $this->dispatchQueuedMessages();
    }

    /**
     * Dispatch any queued messages for security runs when capacity is available.
     * This handles the case where messages were queued because we were at max concurrent tasks.
     */
    private function dispatchQueuedMessages(): void
    {
        if (! $this->canDispatchSecurityTask()) {
            return;
        }

        // Find runs in researching/fixing_ci that have tasks with queued messages
        // Include both Pending and Completed tasks (Completed tasks may have queued follow-up messages)
        $runsWithQueuedMessages = SecurityRun::query()
            ->whereIn('status', [
                SecurityRunStatus::Researching->value,
                SecurityRunStatus::FixingCi->value,
            ])
            ->whereNotNull('task_id')
            ->with('task')
            ->get()
            ->filter(function ($run) {
                if (! $run->task) {
                    return false;
                }

                // Skip running tasks - they're already being processed
                if ($run->task->status === TaskStatus::Running) {
                    return false;
                }

                return $run->task->messages()
                    ->where('status', MessageStatus::Queued->value)
                    ->exists();
            });

        foreach ($runsWithQueuedMessages as $run) {
            if (! $this->canDispatchSecurityTask()) {
                break;
            }

            $queuedMessage = $run->task->messages()
                ->where('status', MessageStatus::Queued->value)
                ->first();

            if ($queuedMessage) {
                $queuedMessage->update(['status' => MessageStatus::Sent]);
                $run->task->dispatchMessage($queuedMessage);

                Log::info("Dispatched queued message for PR #{$run->github_pr_number}");
            }
        }
    }

    /**
     * Create or get a dedicated task for a specific SecurityRun.
     */
    private function ensureTaskForRun(SecurityRun $run, Repository $repo): Task
    {
        // If run already has a task, reuse it
        if ($run->task_id) {
            $existingTask = Task::find($run->task_id);
            if ($existingTask) {
                return $existingTask;
            }
        }

        // Create a new task specifically for this PR
        $task = Task::create([
            'title' => "Security PR #{$run->github_pr_number}: {$run->pr_title}",
            'repository_id' => $repo->id,
            'ai_provider_id' => $this->aiResolver->orchestratorProvider()?->id,
            'status' => TaskStatus::Pending,
        ]);

        $run->update(['task_id' => $task->id]);

        return $task;
    }

    /**
     * Check if any tracked PRs have been closed on GitHub and update their status.
     */
    private function syncClosedPrs(Repository $repo, GitHubService $github): void
    {
        $activeRuns = SecurityRun::query()
            ->where('repository_id', $repo->id)
            ->whereIn('status', [
                SecurityRunStatus::NeedsUserAction->value,
                SecurityRunStatus::Researching->value,
                SecurityRunStatus::FixingCi->value,
                SecurityRunStatus::WaitingCi->value,
                SecurityRunStatus::Pending->value,
            ])
            ->get();

        foreach ($activeRuns as $run) {
            try {
                $pr = $github->fetchPullRequest($repo->full_name, $run->github_pr_number);

                if (! $pr) {
                    continue;
                }

                if (($pr['state'] ?? 'open') === 'closed' && ! ($pr['merged'] ?? false)) {
                    $run->update([
                        'status' => SecurityRunStatus::Closed,
                        'error_message' => 'PR was closed on GitHub',
                    ]);

                    Log::info("Marked PR #{$run->github_pr_number} as Closed (detected from GitHub)");
                }

                if (($pr['merged'] ?? false) && $run->status !== SecurityRunStatus::Merged && $run->status !== SecurityRunStatus::Deployed) {
                    $run->update([
                        'status' => SecurityRunStatus::Merged,
                        'merge_commit_sha' => $pr['merge_commit_sha'] ?? null,
                    ]);

                    Log::info("Marked PR #{$run->github_pr_number} as Merged (detected from GitHub)");
                }
            } catch (\Throwable $e) {
                continue;
            }
        }
    }

    /**
     * Process runs in FixingCi status - check if CI now passes after fixer worked on it.
     */
    private function processFixingCiRuns(Repository $repo, GitHubService $github): void
    {
        $runs = SecurityRun::query()
            ->where('repository_id', $repo->id)
            ->where('status', SecurityRunStatus::FixingCi->value)
            ->get();

        foreach ($runs as $run) {
            $pr = $github->fetchPullRequest($repo->full_name, $run->github_pr_number);
            if (! $pr) {
                continue;
            }

            $headSha = $pr['head']['sha'] ?? null;
            if (! $headSha) {
                continue;
            }

            $status = $github->fetchCombinedStatus($repo->full_name, $headSha);

            // Get or create task for this run
            $task = $run->task;
            if (! $task) {
                // Run is in FixingCi but has no task - create one and dispatch prompt
                $task = $this->ensureTaskForRun($run, $repo);
                $this->dispatchCiFixerPrompt($task, $run, $repo, $pr, $status);

                continue;
            }

            if ($status['state'] === 'success') {
                $run->update([
                    'status' => SecurityRunStatus::Researching,
                    'last_checked_at' => now(),
                ]);

                Message::create([
                    'task_id' => $task->id,
                    'role' => MessageRole::Assistant,
                    'status' => MessageStatus::Sent,
                    'content' => 'CI is now passing. Proceeding with security review.',
                ]);

                $this->dispatchOrchestratorPrompt($task, $run, $repo, $pr, $status);

                continue;
            }

            $ciFixerGaveUp = $this->hasCiFixerGivenUp($task, $run);

            $fixingCiGraceMinutes = (int) config('services.security_ai.fixing_ci_grace_minutes', 30);
            $stuckTooLong = $run->last_checked_at &&
                $run->last_checked_at->diffInMinutes(now()) > $fixingCiGraceMinutes;

            if ($ciFixerGaveUp || $stuckTooLong) {
                $run->update([
                    'status' => SecurityRunStatus::Researching,
                    'last_checked_at' => now(),
                ]);

                $reason = $ciFixerGaveUp
                    ? 'CI fixer could not resolve the issue'
                    : "CI still failing after {$fixingCiGraceMinutes} minutes";

                Message::create([
                    'task_id' => $task->id,
                    'role' => MessageRole::Assistant,
                    'status' => MessageStatus::Sent,
                    'content' => "{$reason}. Proceeding with security review to determine if we should ignore or escalate.",
                ]);

                $this->dispatchOrchestratorPrompt($task, $run, $repo, $pr, $status);

                continue;
            }

            $run->update(['last_checked_at' => now()]);
        }
    }

    /**
     * Check if the CI fixer has responded that it cannot fix the issue.
     */
    private function hasCiFixerGivenUp(Task $task, SecurityRun $run): bool
    {
        $ciFixerPrompt = Message::query()
            ->where('task_id', $task->id)
            ->where('role', MessageRole::User)
            ->where('content', 'like', '%CI Fixer%')
            ->where('created_at', '>', now()->subHours(2))
            ->orderBy('created_at', 'desc')
            ->first();

        if (! $ciFixerPrompt) {
            return false;
        }

        $recentResponses = Message::query()
            ->where('task_id', $task->id)
            ->where('role', MessageRole::Assistant)
            ->where('created_at', '>', $ciFixerPrompt->created_at)
            ->orderBy('created_at', 'asc')
            ->get();

        foreach ($recentResponses as $message) {
            $content = $message->content ?? '';

            if (empty(trim($content))) {
                continue;
            }

            if (preg_match('/```json\s*(.*?)\s*```/s', $content, $matches)) {
                $data = json_decode($matches[1], true);
                if (is_array($data) && array_key_exists('ci_fixed', $data)) {
                    $ciFixed = $data['ci_fixed'] ?? null;
                    $needsManual = $data['needs_manual_intervention'] ?? false;

                    if ($ciFixed === false || $needsManual === true) {
                        return true;
                    }

                    if ($ciFixed === true) {
                        return false;
                    }
                }
            }
        }

        if (! $task->isRunning() && $recentResponses->filter(fn ($m) => ! empty(trim($m->content)))->isEmpty()) {
            return true;
        }

        return false;
    }

    /**
     * Deploy the repository via Ploi.
     */
    private function deployRepository(Repository $repo): bool
    {
        if (! $repo->ploi_site_id || ! $repo->ploi_server_id) {
            try {
                $ploi = new PloiService;
                $found = $ploi->smartResolveSiteForRepository($repo);
                if ($found) {
                    $repo->refresh();
                }
            } catch (\Throwable $e) {
                Log::info("Could not resolve Ploi site for {$repo->name}: {$e->getMessage()}");

                return false;
            }
        }

        if (! $repo->ploi_site_domain || ! $repo->ploi_server_name) {
            return false;
        }

        $command = [
            'ploi', 'deploy',
            '--server='.$repo->ploi_server_name,
            '--site='.$repo->ploi_site_domain,
            '--no-interaction',
        ];

        $result = Process::run($command);

        if ($result->successful()) {
            return true;
        }

        if ($this->isUntrackedMergeError($result->errorOutput())) {
            $ploi = new PloiService;
            $updated = $ploi->ensureDeployScriptCleanup(
                (string) $repo->ploi_server_id,
                (string) $repo->ploi_site_id,
                'rm -rf public/build'
            );

            if ($updated) {
                $result = Process::run($command);

                if ($result->successful()) {
                    return true;
                }
            }
        }

        throw new \RuntimeException('Deploy failed: '.$result->errorOutput());
    }

    private function isUntrackedMergeError(string $errorOutput): bool
    {
        $message = strtolower($errorOutput);

        return str_contains($message, 'untracked working tree files would be overwritten by merge');
    }

    private function processResearchingRuns(Repository $repo, GitHubService $github): void
    {
        $runs = SecurityRun::query()
            ->where('repository_id', $repo->id)
            ->where('status', SecurityRunStatus::Researching->value)
            ->get();

        foreach ($runs as $run) {
            if ($run->decision_summary) {
                continue;
            }

            // Get or create task for this run
            $task = $run->task;
            if (! $task) {
                // Run is in Researching but has no task - create one and dispatch prompt
                $pr = $github->fetchPullRequest($repo->full_name, $run->github_pr_number);
                if (! $pr) {
                    continue;
                }

                $status = $github->fetchCombinedStatus($repo->full_name, $pr['head']['sha'] ?? '');
                $task = $this->ensureTaskForRun($run, $repo);
                $this->dispatchOrchestratorPrompt($task, $run, $repo, $pr, $status);

                continue;
            }

            // Find decision in this run's dedicated task
            $decision = $this->findDecisionForRun($task);
            if (! $decision) {
                continue;
            }

            $run->update([
                'decision_summary' => json_encode($decision, JSON_UNESCAPED_SLASHES),
                'risk_level' => $decision['risk_level'] ?? null,
            ]);

            $updateDetails = $this->determineUpdateDetails($repo, $run, $github);

            $mergeAllowed = filter_var($decision['merge_allowed'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $action = $decision['action'] ?? ($mergeAllowed ? 'merge' : 'escalate');
            $this->postDecisionMessage($task, $run, $decision, $mergeAllowed);

            if ($action === 'ignore') {
                $this->closePrViaComment($github, $repo, $run, $task);
                $run->update(['status' => SecurityRunStatus::Closed]);

                continue;
            }

            if (! $mergeAllowed || $action === 'escalate') {
                if ($updateDetails['update_type'] === 'major') {
                    $this->createMajorUpgradeRun($repo, $run->github_pr_number, []);
                }
                $this->createUserNeededAction($task, $repo, $run, $decision, $updateDetails);
                $run->update(['status' => SecurityRunStatus::NeedsUserAction]);

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
            } catch (\Throwable $e) {
                $run->update([
                    'status' => SecurityRunStatus::Failed,
                    'error_message' => $e->getMessage(),
                ]);

                $this->postFailedMessage($task, $run, $e->getMessage());

                continue;
            }

            try {
                $deployed = $this->deployRepository($repo);

                if ($deployed) {
                    $run->update(['status' => SecurityRunStatus::Deployed]);
                    $this->postDeployedMessage($task, $run);
                } else {
                    Message::create([
                        'task_id' => $task->id,
                        'role' => MessageRole::Assistant,
                        'status' => MessageStatus::Sent,
                        'content' => 'Merged successfully. Deployment skipped (no Ploi site configured).',
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning("Deploy failed for PR #{$run->github_pr_number} after merge", [
                    'error' => $e->getMessage(),
                    'repository' => $repo->full_name,
                ]);

                Message::create([
                    'task_id' => $task->id,
                    'role' => MessageRole::Assistant,
                    'status' => MessageStatus::Sent,
                    'content' => "Merged successfully, but deployment failed: {$e->getMessage()}. Manual deployment may be required.",
                ]);
            }
        }
    }

    /**
     * Find the AI decision in a task's messages.
     * Since each task handles only ONE PR, we just find any valid decision.
     *
     * @return array<string, mixed>|null
     */
    private function findDecisionForRun(Task $task): ?array
    {
        $assistantMessages = Message::query()
            ->where('task_id', $task->id)
            ->where('role', MessageRole::Assistant)
            ->orderBy('id', 'desc')
            ->get();

        foreach ($assistantMessages as $message) {
            $decision = $this->parser->parse($message->content ?? '');
            if ($decision) {
                return $decision;
            }
        }

        return null;
    }

    /**
     * @return array{update_type: string, dependency: string|null, from_version: string|null, to_version: string|null}
     */
    private function determineUpdateDetails(Repository $repo, SecurityRun $run, GitHubService $github): array
    {
        $pr = $github->fetchPullRequest($repo->full_name, $run->github_pr_number);
        $title = $pr['title'] ?? $run->pr_title ?? '';
        $body = $pr['body'] ?? '';

        $dependency = $this->extractDependencyName($title);
        [$from, $to] = $this->extractVersionPair($title, $body);

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
            'content' => 'User action required. See "User needed actions" for options.',
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

    /**
     * Check if we can dispatch a new security task (limit concurrent tasks).
     * Only counts runs where the AI task is actually running, not pending tasks.
     */
    private function canDispatchSecurityTask(): bool
    {
        $maxConcurrent = (int) config('services.security_ai.max_concurrent_tasks', 2);

        $activeRunCount = SecurityRun::query()
            ->whereIn('status', [
                SecurityRunStatus::Researching->value,
                SecurityRunStatus::FixingCi->value,
            ])
            ->whereHas('task', fn ($q) => $q->where('status', TaskStatus::Running->value))
            ->count();

        return $activeRunCount < $maxConcurrent;
    }

    private function dispatchOrchestratorPrompt(Task $task, SecurityRun $run, Repository $repo, array $pr, array $status): void
    {
        $content = $this->buildOrchestratorPrompt($repo, $pr, $status);

        $orchestratorProvider = $this->aiResolver->orchestratorProvider();
        if ($orchestratorProvider) {
            $task->update(['ai_provider_id' => $orchestratorProvider->id]);
        }

        if ($task->isRunning()) {
            Message::create([
                'task_id' => $task->id,
                'role' => MessageRole::User,
                'status' => MessageStatus::Queued,
                'content' => $content,
            ]);

            return;
        }

        if (! $this->canDispatchSecurityTask()) {
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

    private function dispatchCiFixerPrompt(Task $task, SecurityRun $run, Repository $repo, array $pr, array $status): void
    {
        $content = $this->buildCiFixerPrompt($repo, $pr, $status);

        $fixerProvider = $this->aiResolver->fixerProvider();
        if ($fixerProvider) {
            $task->update(['ai_provider_id' => $fixerProvider->id]);
        }

        if ($task->isRunning()) {
            Message::create([
                'task_id' => $task->id,
                'role' => MessageRole::User,
                'status' => MessageStatus::Queued,
                'content' => $content,
            ]);

            return;
        }

        if (! $this->canDispatchSecurityTask()) {
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

    private function buildCiFixerPrompt(Repository $repo, array $pr, array $status): string
    {
        $promptPath = resource_path('prompts/security/ci-fixer.md');
        $template = File::exists($promptPath)
            ? File::get($promptPath)
            : 'You are the CI Fixer. Investigate and fix CI failures.';

        $payload = [
            'repo' => $repo->full_name,
            'pr_number' => $pr['number'] ?? null,
            'head_sha' => $pr['head']['sha'] ?? null,
            'ci_state' => $status['state'] ?? 'unknown',
        ];

        $phpVersion = $this->getPloiPhpVersion($repo);
        if ($phpVersion) {
            $payload['ploi_php_version'] = $phpVersion;
        }

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return rtrim($template)."\n\n```json\n{$json}\n```";
    }

    private function getPloiPhpVersion(Repository $repo): ?string
    {
        if (! $repo->ploi_server_name || ! $repo->ploi_site_domain) {
            try {
                $ploi = new PloiService;
                $found = $ploi->smartResolveSiteForRepository($repo);
                if ($found) {
                    $repo->refresh();
                }
            } catch (\Throwable $e) {
                Log::debug("Could not resolve Ploi site for {$repo->name}: {$e->getMessage()}");

                return null;
            }
        }

        if (! $repo->ploi_server_name || ! $repo->ploi_site_domain) {
            return null;
        }

        try {
            $ploi = new PloiService;
            $sites = $ploi->fetchSitesForServer($repo->ploi_server_name);

            foreach ($sites as $site) {
                if ($site['domain'] === $repo->ploi_site_domain) {
                    return $site['php_version'] ?? null;
                }
            }
        } catch (\Throwable $e) {
            Log::debug("Could not fetch Ploi PHP version for {$repo->name}: {$e->getMessage()}");
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

        $content = "PR #{$number} ({$title}) has no CI checks after {$graceMinutes} minutes. Proceeding with security review.";

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

        $content = "Decision: {$allowedText}. Risk level: {$risk}.";
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
            'content' => "Merged successfully. Commit: {$sha}.",
        ]);
    }

    private function postDeployedMessage(Task $task, SecurityRun $run): void
    {
        Message::create([
            'task_id' => $task->id,
            'role' => MessageRole::Assistant,
            'status' => MessageStatus::Sent,
            'content' => 'Deployed successfully.',
        ]);
    }

    private function postFailedMessage(Task $task, SecurityRun $run, string $message): void
    {
        Message::create([
            'task_id' => $task->id,
            'role' => MessageRole::Assistant,
            'status' => MessageStatus::Sent,
            'content' => "Failed: {$message}",
        ]);
    }

    /**
     * Close a PR by commenting @dependabot close.
     */
    private function closePrViaComment(GitHubService $github, Repository $repo, SecurityRun $run, Task $task): void
    {
        try {
            $github->addPullRequestComment(
                $repo->full_name,
                $run->github_pr_number,
                "@dependabot close\n\nClosing this PR automatically. CI is failing and this update doesn't address a critical security vulnerability. The site is working fine with the current version. We'll pick up this update naturally when it becomes compatible."
            );

            Message::create([
                'task_id' => $task->id,
                'role' => MessageRole::Assistant,
                'status' => MessageStatus::Sent,
                'content' => 'Closed via @dependabot close. CI was failing but no real security risk.',
            ]);
        } catch (\Throwable $e) {
            Log::warning("Failed to close PR #{$run->github_pr_number} via comment", [
                'error' => $e->getMessage(),
                'repository' => $repo->full_name,
            ]);

            Message::create([
                'task_id' => $task->id,
                'role' => MessageRole::Assistant,
                'status' => MessageStatus::Sent,
                'content' => "Attempted to close but failed: {$e->getMessage()}",
            ]);
        }
    }
}
