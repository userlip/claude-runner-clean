<?php

use App\Livewire\Proposals\Form;
use App\Livewire\Proposals\Index;
use App\Models\Proposal;
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

it('redirects unauthenticated users to login for proposals index', function () {
    $this->get('/workbench/proposals')->assertRedirect('/admin/login');
});

it('returns 403 for non-admin users on proposals index', function () {
    $this->actingAs($this->user)->get('/workbench/proposals')->assertForbidden();
});

it('returns 200 for admin users on proposals index', function () {
    $this->actingAs($this->admin)->get('/workbench/proposals')->assertOk();
});

it('returns 403 for non-admin users on proposals create page', function () {
    $this->actingAs($this->user)->get('/workbench/proposals/create')->assertForbidden();
});

it('returns 403 for non-admin users on proposals edit page', function () {
    $proposal = Proposal::factory()->create();
    $this->actingAs($this->user)->get("/workbench/proposals/{$proposal->uuid}/edit")->assertForbidden();
});

// --- List component ---

it('renders the proposals index with proposals table', function () {
    $proposal = Proposal::factory()->create(['title' => 'Test Proposal']);

    $this->actingAs($this->admin);

    Livewire::test(Index::class)
        ->assertSuccessful()
        ->assertSee('Test Proposal');
});

it('filters proposals by search term', function () {
    Proposal::factory()->create(['title' => 'Alpha Proposal']);
    Proposal::factory()->create(['title' => 'Beta Proposal']);

    $this->actingAs($this->admin);

    Livewire::test(Index::class)
        ->set('search', 'Alpha')
        ->assertSee('Alpha Proposal')
        ->assertDontSee('Beta Proposal');
});

it('deletes a proposal', function () {
    $proposal = Proposal::factory()->create();

    $this->actingAs($this->admin);

    Livewire::test(Index::class)
        ->call('delete', $proposal->id);

    $this->assertDatabaseMissing(Proposal::class, ['id' => $proposal->id]);
});

// --- Create form ---

it('creates a new proposal', function () {
    $this->actingAs($this->admin);

    Livewire::test(Form::class)
        ->set('title', 'My New Proposal')
        ->set('description', 'A description')
        ->set('project', 'my-project')
        ->set('priority', 'medium')
        ->set('status', 'pending')
        ->set('type', 'other')
        ->call('save');

    $this->assertDatabaseHas(Proposal::class, [
        'title' => 'My New Proposal',
        'project' => 'my-project',
    ]);
});

it('validates required fields on proposal create', function () {
    $this->actingAs($this->admin);

    Livewire::test(Form::class)
        ->set('title', '')
        ->set('priority', '')
        ->set('status', '')
        ->set('type', '')
        ->call('save')
        ->assertHasErrors(['title', 'priority', 'status', 'type']);
});

// --- Edit form ---

it('loads existing proposal data in edit form', function () {
    $proposal = Proposal::factory()->create([
        'title' => 'Existing Proposal',
        'project' => 'existing-project',
    ]);

    $this->actingAs($this->admin);

    Livewire::test(Form::class, ['uuid' => $proposal->uuid])
        ->assertSet('title', 'Existing Proposal')
        ->assertSet('project', 'existing-project');
});

it('updates an existing proposal', function () {
    $proposal = Proposal::factory()->create(['title' => 'Old Title']);

    $this->actingAs($this->admin);

    Livewire::test(Form::class, ['uuid' => $proposal->uuid])
        ->set('title', 'New Title')
        ->call('save');

    $this->assertDatabaseHas(Proposal::class, [
        'id' => $proposal->id,
        'title' => 'New Title',
    ]);
});
