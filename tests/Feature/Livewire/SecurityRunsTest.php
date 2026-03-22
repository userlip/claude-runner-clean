<?php

use App\Livewire\SecurityRuns\Index;
use App\Models\SecurityRun;
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

it('redirects unauthenticated users to login for security-runs index', function () {
    $this->get('/app/security-runs')->assertRedirect('/admin/login');
});

it('returns 403 for non-admin users on security-runs index', function () {
    $this->actingAs($this->user)->get('/app/security-runs')->assertForbidden();
});

it('returns 200 for admin users on security-runs index', function () {
    $this->actingAs($this->admin)->get('/app/security-runs')->assertOk();
});

// --- List component ---

it('renders the security runs index with table', function () {
    $run = SecurityRun::factory()->create(['pr_title' => 'Bump lodash from 4.17.20 to 4.17.21']);

    $this->actingAs($this->admin);

    Livewire::test(Index::class)
        ->assertSuccessful()
        ->assertSee('Bump lodash from 4.17.20 to 4.17.21');
});

it('shows multiple security runs in the list', function () {
    SecurityRun::factory()->create(['pr_title' => 'Bump package-a from 1.0.0 to 1.0.1']);
    SecurityRun::factory()->create(['pr_title' => 'Bump package-b from 2.0.0 to 2.1.0']);

    $this->actingAs($this->admin);

    Livewire::test(Index::class)
        ->assertSee('Bump package-a from 1.0.0 to 1.0.1')
        ->assertSee('Bump package-b from 2.0.0 to 2.1.0');
});
