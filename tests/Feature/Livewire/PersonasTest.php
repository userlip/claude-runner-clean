<?php

use App\Livewire\Personas\Form;
use App\Livewire\Personas\Index;
use App\Models\Persona;
use App\Models\Repository;
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

it('redirects unauthenticated users to login for personas index', function () {
    $this->get('/workbench/personas')->assertRedirect('/admin/login');
});

it('returns 403 for non-admin users on personas index', function () {
    $this->actingAs($this->user)->get('/workbench/personas')->assertForbidden();
});

it('returns 200 for admin users on personas index', function () {
    $this->actingAs($this->admin)->get('/workbench/personas')->assertOk();
});

it('returns 403 for non-admin users on personas create page', function () {
    $this->actingAs($this->user)->get('/workbench/personas/create')->assertForbidden();
});

it('returns 403 for non-admin users on personas edit page', function () {
    $persona = Persona::factory()->create();
    $this->actingAs($this->user)->get("/workbench/personas/{$persona->slug}/edit")->assertForbidden();
});

// --- List component ---

it('renders the personas index with personas table', function () {
    $persona = Persona::factory()->create(['name' => 'Test Persona']);

    $this->actingAs($this->admin);

    Livewire::test(Index::class)
        ->assertSuccessful()
        ->assertSee('Test Persona');
});

it('filters personas by search term', function () {
    Persona::factory()->create(['name' => 'Alpha Persona']);
    Persona::factory()->create(['name' => 'Beta Persona']);

    $this->actingAs($this->admin);

    Livewire::test(Index::class)
        ->set('search', 'Alpha')
        ->assertSee('Alpha Persona')
        ->assertDontSee('Beta Persona');
});

it('deletes a persona', function () {
    $persona = Persona::factory()->create();

    $this->actingAs($this->admin);

    Livewire::test(Index::class)
        ->call('delete', $persona->id);

    $this->assertDatabaseMissing(Persona::class, ['id' => $persona->id]);
});

// --- Create form ---

it('creates a new persona', function () {
    $repository = Repository::factory()->create(['user_id' => $this->admin->id]);

    $this->actingAs($this->admin);

    Livewire::test(Form::class)
        ->set('name', 'My New Persona')
        ->set('description', 'A description')
        ->set('repository_id', $repository->id)
        ->set('status', 'active')
        ->set('is_active', true)
        ->call('save');

    $this->assertDatabaseHas(Persona::class, [
        'name' => 'My New Persona',
    ]);
});

it('validates required fields on persona create', function () {
    $this->actingAs($this->admin);

    Livewire::test(Form::class)
        ->set('name', '')
        ->set('status', '')
        ->call('save')
        ->assertHasErrors(['name', 'status']);
});

// --- Edit form ---

it('loads existing persona data in edit form', function () {
    $persona = Persona::factory()->create([
        'name' => 'Existing Persona',
        'description' => 'Existing description',
    ]);

    $this->actingAs($this->admin);

    Livewire::test(Form::class, ['slug' => $persona->slug])
        ->assertSet('name', 'Existing Persona')
        ->assertSet('description', 'Existing description');
});

it('updates an existing persona', function () {
    $persona = Persona::factory()->create(['name' => 'Old Name']);

    $this->actingAs($this->admin);

    Livewire::test(Form::class, ['slug' => $persona->slug])
        ->set('name', 'New Name')
        ->call('save');

    $this->assertDatabaseHas(Persona::class, [
        'id' => $persona->id,
        'name' => 'New Name',
    ]);
});
