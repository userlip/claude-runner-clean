<?php

use App\Enums\MajorUpgradeStatus;
use App\Models\MajorUpgradeRun;
use App\Models\Repository;
use App\Models\Task;
use App\Services\SecurityManagementService;

it('creates a major upgrade run and task for major updates', function () {
    $repo = Repository::factory()->create(['security_management_enabled' => true]);

    $service = app(SecurityManagementService::class);
    $service->createMajorUpgradeRunForTest($repo, 101);

    $run = MajorUpgradeRun::where('repository_id', $repo->id)->first();

    expect($run)->not->toBeNull();
    expect($run->status)->toBe(MajorUpgradeStatus::Pending);
    expect(Task::where('id', $run->created_by_task_id)->exists())->toBeTrue();
});
