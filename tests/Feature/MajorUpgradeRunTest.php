<?php

use App\Enums\MajorUpgradeStatus;
use App\Models\MajorUpgradeRun;
use App\Models\Repository;

it('creates a major upgrade run with default status', function () {
    $repo = Repository::factory()->create();

    $run = MajorUpgradeRun::create([
        'repository_id' => $repo->id,
        'github_pr_number' => 123,
        'status' => MajorUpgradeStatus::Pending,
    ]);

    expect($run->status)->toBe(MajorUpgradeStatus::Pending);
});
