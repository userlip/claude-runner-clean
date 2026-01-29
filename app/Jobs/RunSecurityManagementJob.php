<?php

namespace App\Jobs;

use App\Enums\SecurityRunStatus;
use App\Models\Repository;
use App\Models\SecurityRun;
use App\Services\MajorUpgradeService;
use App\Services\SecurityManagementService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Cache;

class RunSecurityManagementJob implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public function __construct(private ?int $repoId = null) {}

    public function handle(SecurityManagementService $service, MajorUpgradeService $majorUpgradeService): void
    {
        // Check if there are already active security runs - if so, don't start more
        // This ensures we process 1 at a time instead of hammering the API
        $maxConcurrent = (int) config('services.security_ai.max_concurrent_tasks', 2);
        $activeRuns = SecurityRun::query()
            ->whereIn('status', [
                SecurityRunStatus::Researching->value,
                SecurityRunStatus::FixingCi->value,
            ])
            ->count();

        if ($activeRuns >= $maxConcurrent) {
            // Already at max capacity, don't process more repos
            return;
        }

        $query = Repository::where('security_management_enabled', true);

        if ($this->repoId) {
            $query->whereKey($this->repoId);
            $repo = $query->first();
        } else {
            // Round-robin through repositories using a cached offset
            $repo = $this->getNextRepository($query);
        }

        if ($repo) {
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
