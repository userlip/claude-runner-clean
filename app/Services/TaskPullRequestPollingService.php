<?php

namespace App\Services;

use App\Enums\MessageRole;
use App\Enums\MessageStatus;
use App\Enums\TaskStatus;
use App\Models\Message;
use App\Models\Task;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TaskPullRequestPollingService
{
    public function poll(): void
    {
        // Avoid brittle JSON DB queries (sqlite/mysql differences): filter in PHP.
        $tasks = Task::query()
            ->whereNotNull('repository_id')
            ->whereNotNull('session_metadata')
            ->get();

        foreach ($tasks as $task) {
            $monitor = $task->session_metadata['pr_monitor'] ?? null;

            // Backfill for older tasks (or tasks where the PR URL arrived after completion):
            // if a task is completed/failed and doesn't have a monitor yet, try to detect a PR URL
            // from recent messages and store it, then continue polling as normal.
            if (! is_array($monitor) && in_array($task->status, [TaskStatus::Completed, TaskStatus::Failed], true)) {
                try {
                    app(TaskPullRequestDetectionService::class)->detectAndStore($task);
                } catch (\Throwable $e) {
                    // Best-effort only.
                }

                $task->refresh();
                $monitor = $task->session_metadata['pr_monitor'] ?? null;
            }

            if (! is_array($monitor) || ! ($monitor['active'] ?? false)) {
                continue;
            }

            // Don't interrupt tasks that are waiting on explicit user input.
            if ($task->status === TaskStatus::WaitingForInput) {
                continue;
            }

            $repo = $task->repository;
            $connection = $repo?->user?->githubConnection;
            if (! $repo || ! $connection) {
                continue;
            }

            $fullName = (string) ($monitor['repository_full_name'] ?? $repo->full_name ?? '');
            $prNumber = (int) ($monitor['pr_number'] ?? 0);
            if ($fullName === '' || $prNumber <= 0) {
                continue;
            }

            try {
                $github = new GitHubService($connection);

                $pr = $github->fetchPullRequest($fullName, $prNumber);
                $state = (string) ($pr['state'] ?? 'open');
                $merged = (bool) ($pr['merged'] ?? false);

                if ($state !== 'open' || $merged) {
                    $this->deactivateMonitor($task, $monitor, reason: 'pr_closed_or_merged');

                    continue;
                }

                $headSha = (string) ($pr['head']['sha'] ?? '');
                if ($headSha === '') {
                    continue;
                }

                $status = $github->fetchCombinedStatus($fullName, $headSha);
                $ciState = (string) ($status['state'] ?? 'pending');

                if ($ciState === 'pending') {
                    $this->updateMonitor($task, $monitor, [
                        'last_seen_head_sha' => $headSha,
                        'last_polled_at' => now()->toIso8601String(),
                    ]);

                    continue;
                }

                if (($monitor['last_notified_sha'] ?? null) === $headSha) {
                    // Already notified for this head SHA.
                    $this->updateMonitor($task, $monitor, [
                        'last_seen_head_sha' => $headSha,
                        'last_polled_at' => now()->toIso8601String(),
                    ]);

                    continue;
                }

                $checkRunsPayload = $github->fetchCheckRuns($fullName, $headSha);
                $failingChecks = $this->extractFailingCheckRuns($checkRunsPayload['check_runs'] ?? []);

                $reviews = $github->fetchPullRequestReviews($fullName, $prNumber);
                $issueComments = $github->fetchIssueComments($fullName, $prNumber, $monitor['last_issue_comment_since'] ?? null);

                $body = $this->buildUserNudgeMessage(
                    fullName: $fullName,
                    prNumber: $prNumber,
                    headSha: $headSha,
                    ciState: $ciState,
                    failingChecks: $failingChecks,
                    reviews: $reviews,
                    issueComments: $issueComments,
                );

                $shouldQueueOnly = $task->status === TaskStatus::Running;
                $message = Message::create([
                    'task_id' => $task->id,
                    'role' => MessageRole::User,
                    'status' => $shouldQueueOnly ? MessageStatus::Queued : MessageStatus::Sent,
                    'content' => $body,
                ]);

                // Mark as notified before dispatch to prevent duplicate messages if dispatch fails.
                $this->updateMonitor($task, $monitor, [
                    'last_notified_sha' => $headSha,
                    'last_notified_state' => $ciState,
                    'last_notified_at' => now()->toIso8601String(),
                    'last_seen_head_sha' => $headSha,
                    'last_polled_at' => now()->toIso8601String(),
                    // We use a simple time-based marker for issue comments; best-effort.
                    'last_issue_comment_since' => now()->toIso8601String(),
                ]);

                if (! $shouldQueueOnly) {
                    $task->dispatchMessage($message, continue: true);
                }
            } catch (\Throwable $e) {
                Log::warning('Task PR polling failed', [
                    'task_id' => $task->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $runs
     * @return array<int, array{name: string, conclusion: string|null, status: string|null}>
     */
    private function extractFailingCheckRuns(array $runs): array
    {
        $failConclusions = ['failure', 'cancelled', 'timed_out', 'action_required', 'stale'];
        $out = [];

        foreach ($runs as $run) {
            $conclusion = $run['conclusion'] ?? null;
            $status = $run['status'] ?? null;

            // If conclusion is missing but overall CI is non-pending, still surface non-completed runs.
            $isFail = $conclusion !== null && in_array($conclusion, $failConclusions, true);
            if ($isFail || ($conclusion === null && $status !== 'completed')) {
                $out[] = [
                    'name' => (string) ($run['name'] ?? 'Unknown'),
                    'conclusion' => $conclusion !== null ? (string) $conclusion : null,
                    'status' => $status !== null ? (string) $status : null,
                ];
            }
        }

        return $out;
    }

    /**
     * @param  array<int, array<string, mixed>>  $failingChecks
     * @param  array<int, array<string, mixed>>  $reviews
     * @param  array<int, array<string, mixed>>  $issueComments
     */
    private function buildUserNudgeMessage(
        string $fullName,
        int $prNumber,
        string $headSha,
        string $ciState,
        array $failingChecks,
        array $reviews,
        array $issueComments
    ): string {
        $prUrl = "https://github.com/{$fullName}/pull/{$prNumber}";
        $shortSha = Str::substr($headSha, 0, 7);

        $lines = [];
        $lines[] = "The checks in GitHub CI have finished for {$prUrl} (head {$shortSha}).";
        $lines[] = "CI result: {$ciState}.";

        if (! empty($failingChecks)) {
            $lines[] = '';
            $lines[] = 'Failing checks:';
            foreach (array_slice($failingChecks, 0, 8) as $check) {
                $suffix = $check['conclusion'] ?? $check['status'] ?? 'unknown';
                $lines[] = "- {$check['name']} ({$suffix})";
            }
        }

        $latestReview = $this->latestNonEmptyReview($reviews);
        if ($latestReview !== null) {
            $lines[] = '';
            $state = (string) ($latestReview['state'] ?? 'UNKNOWN');
            $body = trim((string) ($latestReview['body'] ?? ''));
            $lines[] = "Latest PR review: {$state}.".($body !== '' ? ' '.Str::limit($body, 180) : '');
        }

        $latestComment = $this->latestNonEmptyIssueComment($issueComments);
        if ($latestComment !== null) {
            $body = trim((string) ($latestComment['body'] ?? ''));
            if ($body !== '') {
                $lines[] = '';
                $lines[] = 'Latest PR comment: '.Str::limit($body, 180);
            }
        }

        $lines[] = '';
        $lines[] = 'Please check the latest code review in the PR (if available) and check out the result of the test suite. Act if you see something wrong.';

        return implode("\n", $lines);
    }

    /**
     * @param  array<int, array<string, mixed>>  $reviews
     * @return array<string, mixed>|null
     */
    private function latestNonEmptyReview(array $reviews): ?array
    {
        $latest = null;
        $latestAt = null;

        foreach ($reviews as $review) {
            $submittedAt = $review['submitted_at'] ?? $review['submittedAt'] ?? null;
            $ts = $submittedAt ? strtotime((string) $submittedAt) : null;
            if ($ts === null) {
                continue;
            }

            if ($latestAt === null || $ts > $latestAt) {
                $latestAt = $ts;
                $latest = $review;
            }
        }

        return $latest;
    }

    /**
     * @param  array<int, array<string, mixed>>  $comments
     * @return array<string, mixed>|null
     */
    private function latestNonEmptyIssueComment(array $comments): ?array
    {
        $latest = null;
        $latestAt = null;

        foreach ($comments as $comment) {
            $updatedAt = $comment['updated_at'] ?? $comment['created_at'] ?? null;
            $ts = $updatedAt ? strtotime((string) $updatedAt) : null;
            if ($ts === null) {
                continue;
            }

            if ($latestAt === null || $ts > $latestAt) {
                $latestAt = $ts;
                $latest = $comment;
            }
        }

        return $latest;
    }

    /**
     * @param  array<string, mixed>  $monitor
     * @param  array<string, mixed>  $updates
     */
    private function updateMonitor(Task $task, array $monitor, array $updates): void
    {
        $task->update([
            'session_metadata' => array_merge($task->session_metadata ?? [], [
                'pr_monitor' => array_merge($monitor, $updates),
            ]),
        ]);
    }

    /**
     * @param  array<string, mixed>  $monitor
     */
    private function deactivateMonitor(Task $task, array $monitor, string $reason): void
    {
        $this->updateMonitor($task, $monitor, [
            'active' => false,
            'deactivated_at' => now()->toIso8601String(),
            'deactivated_reason' => $reason,
        ]);
    }
}
