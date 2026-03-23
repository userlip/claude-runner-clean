<?php

use App\Livewire\Snippets\Form;
use App\Livewire\Snippets\Index;
use App\Models\Snippet;
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

it('redirects unauthenticated users to login for snippets index', function () {
    $this->get('/workbench/snippets')->assertRedirect('/admin/login');
});

it('returns 403 for non-admin users on snippets index', function () {
    $this->actingAs($this->user)->get('/workbench/snippets')->assertForbidden();
});

it('returns 200 for admin users on snippets index', function () {
    $this->actingAs($this->admin)->get('/workbench/snippets')->assertOk();
});

it('returns 403 for non-admin users on snippets create page', function () {
    $this->actingAs($this->user)->get('/workbench/snippets/create')->assertForbidden();
});

it('returns 403 for non-admin users on snippets edit page', function () {
    $snippet = Snippet::factory()->create();
    $this->actingAs($this->user)->get("/workbench/snippets/{$snippet->id}/edit")->assertForbidden();
});

// --- List component ---

it('renders the snippets index with snippets table', function () {
    $snippet = Snippet::factory()->create(['name' => 'Test Snippet']);

    $this->actingAs($this->admin);

    Livewire::test(Index::class)
        ->assertSuccessful()
        ->assertSee('Test Snippet');
});

it('filters snippets by search term', function () {
    Snippet::factory()->create(['name' => 'Alpha Snippet']);
    Snippet::factory()->create(['name' => 'Beta Snippet']);

    $this->actingAs($this->admin);

    Livewire::test(Index::class)
        ->set('search', 'Alpha')
        ->assertSee('Alpha Snippet')
        ->assertDontSee('Beta Snippet');
});

it('deletes a snippet', function () {
    $snippet = Snippet::factory()->create();

    $this->actingAs($this->admin);

    Livewire::test(Index::class)
        ->call('delete', $snippet->id);

    $this->assertDatabaseMissing(Snippet::class, ['id' => $snippet->id]);
});

// --- Create form ---

it('creates a new snippet', function () {
    $this->actingAs($this->admin);

    Livewire::test(Form::class)
        ->set('name', 'My New Snippet')
        ->set('content', 'Snippet content here')
        ->set('sortOrder', 1)
        ->call('save');

    $this->assertDatabaseHas(Snippet::class, [
        'name' => 'My New Snippet',
        'content' => 'Snippet content here',
        'sort_order' => 1,
    ]);
});

it('validates required fields on snippet create', function () {
    $this->actingAs($this->admin);

    Livewire::test(Form::class)
        ->set('name', '')
        ->set('content', '')
        ->call('save')
        ->assertHasErrors(['name', 'content']);
});

// --- Edit form ---

it('loads existing snippet data in edit form', function () {
    $snippet = Snippet::factory()->create([
        'name' => 'Existing Snippet',
        'content' => 'Existing content',
        'sort_order' => 3,
    ]);

    $this->actingAs($this->admin);

    Livewire::test(Form::class, ['id' => $snippet->id])
        ->assertSet('name', 'Existing Snippet')
        ->assertSet('content', 'Existing content')
        ->assertSet('sortOrder', 3);
});

it('updates an existing snippet', function () {
    $snippet = Snippet::factory()->create(['name' => 'Old Name']);

    $this->actingAs($this->admin);

    Livewire::test(Form::class, ['id' => $snippet->id])
        ->set('name', 'New Name')
        ->call('save');

    $this->assertDatabaseHas(Snippet::class, [
        'id' => $snippet->id,
        'name' => 'New Name',
    ]);
});
