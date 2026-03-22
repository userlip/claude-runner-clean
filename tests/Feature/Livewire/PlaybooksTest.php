<?php

use App\Livewire\Playbooks\Form;
use App\Livewire\Playbooks\Index;
use App\Models\Playbook;
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

it('redirects unauthenticated users to login for playbooks index', function () {
    $this->get('/app/playbooks')->assertRedirect('/admin/login');
});

it('returns 403 for non-admin users on playbooks index', function () {
    $this->actingAs($this->user)->get('/app/playbooks')->assertForbidden();
});

it('returns 200 for admin users on playbooks index', function () {
    $this->actingAs($this->admin)->get('/app/playbooks')->assertOk();
});

it('returns 403 for non-admin users on playbooks create page', function () {
    $this->actingAs($this->user)->get('/app/playbooks/create')->assertForbidden();
});

it('returns 403 for non-admin users on playbooks edit page', function () {
    $playbook = Playbook::factory()->create();
    $this->actingAs($this->user)->get("/app/playbooks/{$playbook->id}/edit")->assertForbidden();
});

// --- List component ---

it('renders the playbooks index with playbooks table', function () {
    $playbook = Playbook::factory()->create(['name' => 'Test Playbook']);

    $this->actingAs($this->admin);

    Livewire::test(Index::class)
        ->assertSuccessful()
        ->assertSee('Test Playbook');
});

it('filters playbooks by search term', function () {
    Playbook::factory()->create(['name' => 'Alpha Playbook']);
    Playbook::factory()->create(['name' => 'Beta Playbook']);

    $this->actingAs($this->admin);

    Livewire::test(Index::class)
        ->set('search', 'Alpha')
        ->assertSee('Alpha Playbook')
        ->assertDontSee('Beta Playbook');
});

it('deletes a playbook', function () {
    $playbook = Playbook::factory()->create();

    $this->actingAs($this->admin);

    Livewire::test(Index::class)
        ->call('delete', $playbook->id);

    $this->assertDatabaseMissing(Playbook::class, ['id' => $playbook->id]);
});

// --- Create form ---

it('creates a new playbook', function () {
    $this->actingAs($this->admin);

    Livewire::test(Form::class)
        ->set('name', 'My New Playbook')
        ->set('proposal_type', 'other')
        ->set('description', 'A description')
        ->set('is_active', true)
        ->call('save');

    $this->assertDatabaseHas(Playbook::class, [
        'name' => 'My New Playbook',
    ]);
});

it('validates required fields on playbook create', function () {
    $this->actingAs($this->admin);

    Livewire::test(Form::class)
        ->set('name', '')
        ->set('proposal_type', '')
        ->call('save')
        ->assertHasErrors(['name', 'proposal_type']);
});

// --- Edit form ---

it('loads existing playbook data in edit form', function () {
    $playbook = Playbook::factory()->create([
        'name' => 'Existing Playbook',
        'description' => 'Existing description',
    ]);

    $this->actingAs($this->admin);

    Livewire::test(Form::class, ['id' => $playbook->id])
        ->assertSet('name', 'Existing Playbook')
        ->assertSet('description', 'Existing description');
});

it('updates an existing playbook', function () {
    $playbook = Playbook::factory()->create(['name' => 'Old Name']);

    $this->actingAs($this->admin);

    Livewire::test(Form::class, ['id' => $playbook->id])
        ->set('name', 'New Name')
        ->call('save');

    $this->assertDatabaseHas(Playbook::class, [
        'id' => $playbook->id,
        'name' => 'New Name',
    ]);
});
