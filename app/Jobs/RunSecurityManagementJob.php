<?php

namespace App\Jobs;

use App\Enums\SecurityRunStatus;
use App\Enums\TaskStatus;
use App\Models\Repository;
use App\Models\SecurityRun;
use App\Services\MajorUpgradeService;
use App\Services\SecurityManagementService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RunSecurityManagementJob implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public function __construct(private ?int $repoId = null) {}

    public function handle(SecurityManagementService $service, MajorUpgradeService $majorUpgradeService): void
    {
        // Check if there are already active security runs with running tasks
        // Only count runs where AI task is actually running, not pending
        $maxConcurrent = (int) config('services.security_ai.max_concurrent_tasks', 2);
        $activeRuns = SecurityRun::query()
            ->whereIn('status', [
                SecurityRunStatus::Researching->value,
                SecurityRunStatus::FixingCi->value,
            ])
            ->whereHas('task', fn ($q) => $q->where('status', 'running'))
            ->count();

        Log::debug('SecurityManagement: Job started', [
            'repo_id' => $this->repoId,
            'active_runs' => $activeRuns,
            'max_concurrent' => $maxConcurrent,
        ]);

        if ($activeRuns >= $maxConcurrent) {
            Log::debug('SecurityManagement: Skipping - at max capacity');

            return;
        }

        // If specific repo requested, only process that one
        if ($this->repoId) {
            $repo = Repository::where('security_management_enabled', true)
                ->whereKey($this->repoId)
                ->first();

            if ($repo) {
                $service->processRepository($repo);
                $majorUpgradeService->dispatchPendingRunsForRepository($repo);
            }

            return;
        }

        // Process ALL repos with completed tasks first (decision processing is fast)
        $this->processAllReposWithCompletedTasks($service, $majorUpgradeService);

        // Then do one round-robin repo for new PR discovery
        $query = Repository::where('security_management_enabled', true);
        $repo = $this->getNextRepository($query);

        if ($repo) {
            $service->processRepository($repo);
            $majorUpgradeService->dispatchPendingRunsForRepository($repo);
        }
    }

    /**
     * Process all repositories that have completed tasks waiting for decision processing.
     */
    private function processAllReposWithCompletedTasks(SecurityManagementService $service, MajorUpgradeService $majorUpgradeService): void
    {
        $processedRepoIds = [];

        // Keep processing until no more completed tasks
        while (true) {
            $runWithCompletedTask = SecurityRun::query()
                ->whereIn('status', [
                    SecurityRunStatus::Researching->value,
                    SecurityRunStatus::FixingCi->value,
                ])
                ->whereNull('decision_summary')
                ->whereHas('task', fn ($q) => $q->where('status', TaskStatus::Completed->value))
                ->whereNotIn('repository_id', $processedRepoIds)
                ->with('repository')
                ->first();

            if (! $runWithCompletedTask) {
                Log::debug('SecurityManagement: No more completed tasks to process', [
                    'processed_repo_ids' => $processedRepoIds,
                ]);
                break;
            }

            $repo = $runWithCompletedTask->repository;
            $processedRepoIds[] = $repo->id;

            Log::info('SecurityManagement: Processing repo with completed task', [
                'repo' => $repo->full_name,
                'run_id' => $runWithCompletedTask->id,
                'pr_number' => $runWithCompletedTask->github_pr_number,
            ]);

            $service->processRepository($repo);
            $majorUpgradeService->dispatchPendingRunsForRepository($repo);
        }
    }

    /**
     * Get the next repository to process using round-robin rotation.
     */
    private function getNextRepository($query): ?Repository
    {
        $cacheKey = 'security_management_last_repo_id';
        $lastProcessedId = Cache::get($cacheKey, 0);

        // Get repository with ID greater than last processed, ordered by ID
        $repo = (clone $query)
            ->where('id', '>', $lastProcessedId)
            ->orderBy('id')
            ->first();

        // If no repository found (reached the end), wrap around to the beginning
        if (! $repo) {
            $repo = (clone $query)
                ->orderBy('id')
                ->first();
        }

        // Cache the current repository ID for next iteration
        if ($repo) {
            Cache::put($cacheKey, $repo->id, now()->addHours(24));
        }

        return $repo;
    }
}
