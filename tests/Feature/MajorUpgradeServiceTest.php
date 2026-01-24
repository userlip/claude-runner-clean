<?php

use App\Enums\MajorUpgradeStatus;
use App\Models\MajorUpgradeRun;
use App\Models\Repository;
use App\Models\Task;
use App\Services\MajorUpgradeService;

it('dispatches orchestrator prompt for major upgrade run', function () {
    $repo = Repository::factory()->create();
    $task = Task::factory()->running()->create([
        'repository_id' => $repo->id,
        'user_id' => $repo->user_id,
    ]);

    $run = MajorUpgradeRun::create([
        'repository_id' => $repo->id,
        'github_pr_number' => 50,
        'status' => MajorUpgradeStatus::Pending,
        'created_by_task_id' => $task->id,
    ]);

    $service = app(MajorUpgradeService::class);
    $service->dispatchOrchestratorPrompt($run);

    $this->assertDatabaseHas('messages', [
        'task_id' => $task->id,
        'role' => 'user',
    ]);

    expect($run->fresh()->status)->toBe(MajorUpgradeStatus::Researching);
});
