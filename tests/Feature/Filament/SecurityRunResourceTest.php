<?php

use App\Filament\Resources\SecurityRunResource\Pages\ListSecurityRuns;
use App\Models\Repository;
use App\Models\SecurityRun;
use App\Models\User;
use Filament\Facades\Filament;

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
