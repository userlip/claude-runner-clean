<?php

use App\Filament\Resources\MajorUpgradeRunResource\Pages\ListMajorUpgradeRuns;
use App\Models\MajorUpgradeRun;
use App\Models\Repository;
use App\Models\User;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('shows major upgrade runs', function () {
    $repo = Repository::factory()->create(['user_id' => $this->user->id]);
    $run = MajorUpgradeRun::create([
        'repository_id' => $repo->id,
        'github_pr_number' => 1,
        'status' => \App\Enums\MajorUpgradeStatus::Pending,
    ]);

    livewire(ListMajorUpgradeRuns::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$run]);
});
