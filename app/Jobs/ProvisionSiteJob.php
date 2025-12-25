<?php

namespace App\Jobs;

use App\Models\Site;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProvisionSiteJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public Site $site) {}

    public function handle(): void
    {
        // TODO: Implement site provisioning via Ploi API
    }
}
