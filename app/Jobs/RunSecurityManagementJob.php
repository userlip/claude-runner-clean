<?php

namespace App\Jobs;

use App\Models\Repository;
use App\Services\SecurityManagementService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class RunSecurityManagementJob implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public function __construct(private ?int $repoId = null) {}

    public function handle(SecurityManagementService $service): void
    {
        $query = Repository::where('security_management_enabled', true);

        if ($this->repoId) {
            $query->whereKey($this->repoId);
        }

        $query->each(fn (Repository $repo) => $service->processRepository($repo));
    }
}
