<?php

use App\Filament\Resources\SecurityRunResource\Pages\ListSecurityRuns;
use App\Jobs\RunSecurityManagementJob;
use App\Models\Repository;
use App\Models\SecurityRun;
use App\Models\Task;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Queue;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('can view security runs list', function () {
    $repo = Repository::factory()->create(['user_id' => $this->user->id]);

    // Create an active run (should be visible by default)
    $activeRun = SecurityRun::create([
        'repository_id' => $repo->id,
        'github_pr_id' => 1,
        'github_pr_number' => 24,
        'status' => 'waiting_ci',
    ]);

    // Create a completed run (should be hidden by default)
    $completedRun = SecurityRun::create([
        'repository_id' => $repo->id,
        'github_pr_id' => 2,
        'github_pr_number' => 25,
        'status' => 'deployed',
    ]);

    livewire(ListSecurityRuns::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$activeRun])
        ->assertCanNotSeeTableRecords([$completedRun]);
});

test('can view completed runs with filter', function () {
    $repo = Repository::factory()->create(['user_id' => $this->user->id]);

    $completedRun = SecurityRun::create([
        'repository_id' => $repo->id,
        'github_pr_id' => 1,
        'github_pr_number' => 24,
        'status' => 'deployed',
    ]);

    livewire(ListSecurityRuns::class)
        ->filterTable('show_completed', true)
        ->assertCanSeeTableRecords([$completedRun]);
});

test('view chat action is visible when run has a dedicated task even if repository security task is null', function () {
    $repo = Repository::factory()->create([
        'user_id' => $this->user->id,
        'security_task_id' => null,
    ]);

    $task = Task::factory()->create(['repository_id' => $repo->id]);

    $run = SecurityRun::create([
        'repository_id' => $repo->id,
        'task_id' => $task->id,
        'github_pr_id' => 3,
        'github_pr_number' => 26,
        'status' => 'researching',
    ]);

    livewire(ListSecurityRuns::class)
        ->assertCanSeeTableRecords([$run])
        ->assertTableActionVisible('viewChat', $run);
});

test('rerun stuck action resets actionable runs and queues orchestration', function () {
    Queue::fake();

    $enabledRepoA = Repository::factory()->create([
        'user_id' => $this->user->id,
        'security_management_enabled' => true,
    ]);
    $enabledRepoB = Repository::factory()->create([
        'user_id' => $this->user->id,
        'security_management_enabled' => true,
    ]);

    $stuckRun = SecurityRun::create([
        'repository_id' => $enabledRepoA->id,
        'github_pr_id' => 100,
        'github_pr_number' => 100,
        'status' => 'failed',
        'decision_summary' => '{"merge_allowed":false}',
        'risk_level' => 'high',
        'error_message' => 'rate limit',
    ]);

    $completedRun = SecurityRun::create([
        'repository_id' => $enabledRepoB->id,
        'github_pr_id' => 101,
        'github_pr_number' => 101,
        'status' => 'merged',
    ]);

    livewire(ListSecurityRuns::class)
        ->callTableAction('rerunStuck')
        ->assertNotified();

    expect($stuckRun->fresh()->status->value)->toBe('pending');
    expect($stuckRun->fresh()->decision_summary)->toBeNull();
    expect($stuckRun->fresh()->risk_level)->toBeNull();
    expect($stuckRun->fresh()->error_message)->toBeNull();
    expect($completedRun->fresh()->status->value)->toBe('merged');

    Queue::assertPushed(RunSecurityManagementJob::class, 2);
});
