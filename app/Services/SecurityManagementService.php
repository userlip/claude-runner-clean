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
        $task = $this->ensureSecurityTask($repo);

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

            if ($nextStatus === SecurityRunStatus::FixingCi) {
                $this->dispatchCiFixerPrompt($task, $repo, $pr, $status);

                continue;
            }

            if ($nextStatus === SecurityRunStatus::Researching) {
                if ($pastGrace) {
                    $this->postNoChecksGraceMessage($task, $pr, $graceMinutes);
                }
                $this->dispatchOrchestratorPrompt($task, $repo, $pr, $status);
            }
        }

        $this->processFixingCiRuns($repo, $task, $github);
        $this->processResearchingRuns($repo, $task, $github);
        $this->syncClosedPrs($repo, $github);
    }

    /**
     * Check if any tracked PRs have been closed on GitHub and update their status.
     */
    private function syncClosedPrs(Repository $repo, GitHubService $github): void
    {
        // Get runs that are in non-terminal states (might have been closed externally)
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

                // If PR is closed (not merged), mark as Closed
                if (($pr['state'] ?? 'open') === 'closed' && ! ($pr['merged'] ?? false)) {
                    $run->update([
                        'status' => SecurityRunStatus::Closed,
                        'error_message' => 'PR was closed on GitHub',
                    ]);

                    Log::info("Marked PR #{$run->github_pr_number} as Closed (detected from GitHub)");
                }

                // If PR was merged externally, update status
                if (($pr['merged'] ?? false) && $run->status !== SecurityRunStatus::Merged && $run->status !== SecurityRunStatus::Deployed) {
                    $run->update([
                        'status' => SecurityRunStatus::Merged,
                        'merge_commit_sha' => $pr['merge_commit_sha'] ?? null,
                    ]);

                    Log::info("Marked PR #{$run->github_pr_number} as Merged (detected from GitHub)");
                }
            } catch (\Throwable $e) {
                // PR might not exist anymore, ignore
                continue;
            }
        }
    }

    /**
     * Process runs in FixingCi status - check if CI now passes after fixer worked on it.
     */
    private function processFixingCiRuns(Repository $repo, Task $task, GitHubService $github): void
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

            // If CI now passes, move to Researching
            if ($status['state'] === 'success') {
                $run->update([
                    'status' => SecurityRunStatus::Researching,
                    'last_checked_at' => now(),
                ]);

                Message::create([
                    'task_id' => $task->id,
                    'role' => MessageRole::Assistant,
                    'status' => MessageStatus::Sent,
                    'content' => "CI is now passing for PR #{$run->github_pr_number}. Proceeding with security review.",
                ]);

                // Dispatch the security review prompt
                $this->dispatchOrchestratorPrompt($task, $repo, $pr, $status);

                continue;
            }

            // Check if CI fixer has given up (responded with ci_fixed: false or needs_manual_intervention: true)
            $ciFixerGaveUp = $this->hasCiFixerGivenUp($task, $run);

            // Also check if we've been stuck in FixingCi for too long (grace period)
            $fixingCiGraceMinutes = (int) config('services.security_ai.fixing_ci_grace_minutes', 30);
            $stuckTooLong = $run->last_checked_at &&
                $run->last_checked_at->diffInMinutes(now()) > $fixingCiGraceMinutes;

            if ($ciFixerGaveUp || $stuckTooLong) {
                // CI fixer can't fix it or we're stuck - move to Researching so the main orchestrator can decide
                // The orchestrator will then either IGNORE (close PR) or ESCALATE based on security risk
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
                    'content' => "PR #{$run->github_pr_number}: {$reason}. Proceeding with security review to determine if we should ignore or escalate.",
                ]);

                // Dispatch the orchestrator prompt - it will decide to IGNORE (close PR) or ESCALATE
                $this->dispatchOrchestratorPrompt($task, $repo, $pr, $status);

                continue;
            }

            // Still waiting for CI fixer to respond or complete
            $run->update(['last_checked_at' => now()]);
        }
    }

    /**
     * Check if the CI fixer has responded that it cannot fix the issue,
     * or if a CI fixer prompt was sent but never got a response.
     */
    private function hasCiFixerGivenUp(Task $task, SecurityRun $run): bool
    {
        // Look for CI fixer prompt (user message) for this PR
        $ciFixerPrompt = Message::query()
            ->where('task_id', $task->id)
            ->where('role', MessageRole::User)
            ->where('content', 'like', '%CI Fixer%')
            ->where('content', 'like', "%\"pr_number\": {$run->github_pr_number}%")
            ->where('created_at', '>', now()->subHours(2))
            ->orderBy('created_at', 'desc')
            ->first();

        // If no CI fixer prompt was sent, we haven't even tried yet
        if (! $ciFixerPrompt) {
            return false;
        }

        // Look for CI fixer responses (assistant messages) after the prompt
        $recentResponses = Message::query()
            ->where('task_id', $task->id)
            ->where('role', MessageRole::Assistant)
            ->where('created_at', '>', $ciFixerPrompt->created_at)
            ->orderBy('created_at', 'asc')
            ->get();

        // Check each response to see if it's a CI fixer response for this PR
        foreach ($recentResponses as $message) {
            $content = $message->content ?? '';

            // Skip empty responses
            if (empty(trim($content))) {
                continue;
            }

            // Look for CI fixer JSON response with ci_fixed field
            if (preg_match('/```json\s*(.*?)\s*```/s', $content, $matches)) {
                $data = json_decode($matches[1], true);
                if (is_array($data) && array_key_exists('ci_fixed', $data)) {
                    // This is a CI fixer response
                    $ciFixed = $data['ci_fixed'] ?? null;
                    $needsManual = $data['needs_manual_intervention'] ?? false;

                    if ($ciFixed === false || $needsManual === true) {
                        return true;
                    }

                    // If ci_fixed is true, the fixer succeeded - don't mark as gave up
                    if ($ciFixed === true) {
                        return false;
                    }
                }
            }
        }

        // If task is not running and no CI fixer response came back, consider it "gave up"
        // This handles the case where multiple prompts were sent but the AI didn't respond to all
        if (! $task->isRunning() && $recentResponses->filter(fn ($m) => ! empty(trim($m->content)))->isEmpty()) {
            return true;
        }

        return false;
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

    /**
     * Deploy the repository via Ploi.
     *
     * @return bool True if deployment was attempted and succeeded, false if no deployment configured
     *
     * @throws \RuntimeException If deployment was attempted but failed
     */
    private function deployRepository(Repository $repo): bool
    {
        // Skip deployment if no Ploi site is configured
        if (! $repo->ploi_site_id || ! $repo->ploi_server_id) {
            // Try to smart-resolve the site by searching ALL servers
            try {
                $ploi = new PloiService;
                $found = $ploi->smartResolveSiteForRepository($repo);
                if ($found) {
                    $repo->refresh();
                }
            } catch (\Throwable $e) {
                // Ploi service unavailable - skip deployment
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
                'risk_level' => $decision['risk_level'] ?? null,
            ]);

            $updateDetails = $this->determineUpdateDetails($payload, $repo, $run, $github);

            // Check AI decision
            $mergeAllowed = filter_var($decision['merge_allowed'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $action = $decision['action'] ?? ($mergeAllowed ? 'merge' : 'escalate');
            $this->postDecisionMessage($task, $run, $decision, $mergeAllowed);

            // Handle "ignore" action - close the PR, no real security risk but CI fails
            if ($action === 'ignore') {
                $this->closePrViaComment($github, $repo, $run, $task);
                $run->update(['status' => SecurityRunStatus::Closed]);

                continue;
            }

            // Handle "escalate" action - real security risk, user must decide
            if (! $mergeAllowed || $action === 'escalate') {
                if ($updateDetails['update_type'] === 'major') {
                    $this->createMajorUpgradeRun(
                        $repo,
                        $run->github_pr_number,
                        $payload['pull_request'] ?? $payload
                    );
                }
                $this->createUserNeededAction($task, $repo, $run, $decision, $updateDetails);
                $run->update(['status' => SecurityRunStatus::NeedsUserAction]);

                continue;
            }

            $run->update(['status' => SecurityRunStatus::Approved]);

            // Step 1: Try to merge the PR
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

            // Step 2: Try to deploy (optional - don't fail the run if deploy fails)
            try {
                $deployed = $this->deployRepository($repo);

                if ($deployed) {
                    $run->update(['status' => SecurityRunStatus::Deployed]);
                    $this->postDeployedMessage($task, $run);
                } else {
                    // No deployment configured/available - stay at Merged status
                    Message::create([
                        'task_id' => $task->id,
                        'role' => MessageRole::Assistant,
                        'status' => MessageStatus::Sent,
                        'content' => "PR #{$run->github_pr_number} merged successfully. Deployment skipped (no Ploi site configured for this repository).",
                    ]);
                }
            } catch (\Throwable $e) {
                // Deploy failed but PR is merged - log the error but don't fail the run
                Log::warning("Deploy failed for PR #{$run->github_pr_number} after merge", [
                    'error' => $e->getMessage(),
                    'repository' => $repo->full_name,
                ]);

                Message::create([
                    'task_id' => $task->id,
                    'role' => MessageRole::Assistant,
                    'status' => MessageStatus::Sent,
                    'content' => "PR #{$run->github_pr_number} merged successfully, but deployment failed: {$e->getMessage()}. Manual deployment may be required.",
                ]);
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
        // Get all user messages with PR payloads (in ascending order)
        $userMessages = Message::query()
            ->where('task_id', $task->id)
            ->where('role', MessageRole::User)
            ->orderBy('id')
            ->get();

        // Find all user messages that are PR prompts and their position
        $prPrompts = [];
        $targetIndex = null;
        $targetPayload = null;

        foreach ($userMessages as $index => $userMessage) {
            $payload = $this->extractJsonBlock($userMessage->content ?? '');
            if (! $payload) {
                continue;
            }

            $payloadNumber = $this->extractPrNumber($payload);
            if (! $payloadNumber) {
                continue;
            }

            $prPrompts[] = [
                'id' => $userMessage->id,
                'pr_number' => $payloadNumber,
            ];

            if ($payloadNumber === $run->github_pr_number) {
                $targetIndex = count($prPrompts) - 1;
                $targetPayload = $payload;
            }
        }

        if ($targetIndex === null || ! $targetPayload) {
            return null;
        }

        // Get assistant messages with valid decisions (in order)
        $assistantMessages = Message::query()
            ->where('task_id', $task->id)
            ->where('role', MessageRole::Assistant)
            ->orderBy('id')
            ->get();

        $decisions = [];
        foreach ($assistantMessages as $assistantMessage) {
            $decision = $this->parser->parse($assistantMessage->content ?? '');
            if ($decision) {
                $decisions[] = $decision;
            }
        }

        // Match by position - nth prompt should match nth decision
        if (! isset($decisions[$targetIndex])) {
            return null;
        }

        return [
            'decision' => $decisions[$targetIndex],
            'payload' => $targetPayload,
        ];
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

    /**
     * Check if we can dispatch a new security task (limit concurrent tasks).
     */
    private function canDispatchSecurityTask(Task $currentTask): bool
    {
        $maxConcurrent = (int) config('services.security_ai.max_concurrent_tasks', 2);

        // Count currently running security tasks (excluding the current one if it's already running)
        $runningCount = Task::query()
            ->where('status', TaskStatus::Running)
            ->whereIn('id', Repository::whereNotNull('security_task_id')->pluck('security_task_id'))
            ->where('id', '!=', $currentTask->id)
            ->count();

        return $runningCount < $maxConcurrent;
    }

    private function dispatchOrchestratorPrompt(Task $task, Repository $repo, array $pr, array $status): void
    {
        $content = $this->buildOrchestratorPrompt($repo, $pr, $status);

        // Ensure we're using the orchestrator provider (may have been switched to fixer)
        $orchestratorProvider = $this->aiResolver->orchestratorProvider();
        if ($orchestratorProvider) {
            $task->update(['ai_provider_id' => $orchestratorProvider->id]);
        }

        // If task is already running, queue the message
        if ($task->isRunning()) {
            Message::create([
                'task_id' => $task->id,
                'role' => MessageRole::User,
                'status' => MessageStatus::Queued,
                'content' => $content,
            ]);

            return;
        }

        // Check concurrency limit before dispatching new task
        if (! $this->canDispatchSecurityTask($task)) {
            // Queue the message instead of dispatching - will be processed later
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
     * @param  array<string, mixed>  $pr
     * @param  array<string, mixed>  $status
     */
    private function dispatchCiFixerPrompt(Task $task, Repository $repo, array $pr, array $status): void
    {
        $content = $this->buildCiFixerPrompt($repo, $pr, $status);

        // Switch to CI fixer provider for this message
        $fixerProvider = $this->aiResolver->fixerProvider();
        if ($fixerProvider) {
            $task->update(['ai_provider_id' => $fixerProvider->id]);
        }

        // If task is already running, queue the message
        if ($task->isRunning()) {
            Message::create([
                'task_id' => $task->id,
                'role' => MessageRole::User,
                'status' => MessageStatus::Queued,
                'content' => $content,
            ]);

            return;
        }

        // Check concurrency limit before dispatching new task
        if (! $this->canDispatchSecurityTask($task)) {
            // Queue the message instead of dispatching - will be processed later
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
     * @param  array<string, mixed>  $pr
     * @param  array<string, mixed>  $status
     */
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

        // Include Ploi PHP version if available - helps CI fixer match production env
        $phpVersion = $this->getPloiPhpVersion($repo);
        if ($phpVersion) {
            $payload['ploi_php_version'] = $phpVersion;
        }

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return rtrim($template)."\n\n```json\n{$json}\n```";
    }

    /**
     * Get the PHP version from Ploi for a repository's site.
     */
    private function getPloiPhpVersion(Repository $repo): ?string
    {
        // First ensure repo has Ploi site info
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

    /**
     * @return array<string, mixed>|null
     */
    private function extractJsonBlock(string $content): ?array
    {
        if (! preg_match_all('/```json\n(.*?)\n```/s', $content, $matches)) {
            return null;
        }

        // Return the LAST valid JSON block (the payload is appended at the end)
        $lastValid = null;
        foreach ($matches[1] as $block) {
            $data = json_decode($block, true);
            if (is_array($data)) {
                $lastValid = $data;
            }
        }

        return $lastValid;
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

    /**
     * Close a PR by commenting @dependabot close - used when CI fails but there's no real security risk.
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
                'content' => "Closed PR #{$run->github_pr_number} via @dependabot close. CI was failing but no real security risk - site works fine as-is.",
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
                'content' => "Attempted to close PR #{$run->github_pr_number} but failed: {$e->getMessage()}",
            ]);
        }
    }
}
