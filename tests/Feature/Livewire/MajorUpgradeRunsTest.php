<?php

use App\Livewire\MajorUpgradeRuns\Index;
use App\Models\MajorUpgradeRun;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->user = User::factory()->create();
});

// --- Access control ---

it('redirects unauthenticated users to login for major-upgrade-runs index', function () {
    $this->get('/app/major-upgrade-runs')->assertRedirect('/admin/login');
});

it('returns 403 for non-admin users on major-upgrade-runs index', function () {
    $this->actingAs($this->user)->get('/app/major-upgrade-runs')->assertForbidden();
});

it('returns 200 for admin users on major-upgrade-runs index', function () {
    $this->actingAs($this->admin)->get('/app/major-upgrade-runs')->assertOk();
});

// --- List component ---

it('renders the major upgrade runs index with table', function () {
    $run = MajorUpgradeRun::factory()->create(['github_pr_number' => 42]);

    $this->actingAs($this->admin);

    Livewire::test(Index::class)
        ->assertSuccessful()
        ->assertSee('42');
});

it('shows multiple major upgrade runs in the list', function () {
    MajorUpgradeRun::factory()->create(['github_pr_number' => 100]);
    MajorUpgradeRun::factory()->create(['github_pr_number' => 200]);

    $this->actingAs($this->admin);

    Livewire::test(Index::class)
        ->assertSee('100')
        ->assertSee('200');
});
