<?php

use App\Livewire\PromotionDirectories\Form;
use App\Livewire\PromotionDirectories\Index;
use App\Models\PromotionDirectory;
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

it('redirects unauthenticated users to login for promotion-directories index', function () {
    $this->get('/workbench/promotion-directories')->assertRedirect('/admin/login');
});

it('returns 403 for non-admin users on promotion-directories index', function () {
    $this->actingAs($this->user)->get('/workbench/promotion-directories')->assertForbidden();
});

it('returns 200 for admin users on promotion-directories index', function () {
    $this->actingAs($this->admin)->get('/workbench/promotion-directories')->assertOk();
});

it('returns 403 for non-admin users on promotion-directories create page', function () {
    $this->actingAs($this->user)->get('/workbench/promotion-directories/create')->assertForbidden();
});

it('returns 403 for non-admin users on promotion-directories edit page', function () {
    $directory = PromotionDirectory::factory()->create();
    $this->actingAs($this->user)->get("/workbench/promotion-directories/{$directory->uuid}/edit")->assertForbidden();
});

// --- List component ---

it('renders the promotion directories index with directories table', function () {
    $directory = PromotionDirectory::factory()->create(['name' => 'Test Directory']);

    $this->actingAs($this->admin);

    Livewire::test(Index::class)
        ->assertSuccessful()
        ->assertSee('Test Directory');
});

it('filters directories by search term', function () {
    PromotionDirectory::factory()->create(['name' => 'Alpha Directory']);
    PromotionDirectory::factory()->create(['name' => 'Beta Directory']);

    $this->actingAs($this->admin);

    Livewire::test(Index::class)
        ->set('search', 'Alpha')
        ->assertSee('Alpha Directory')
        ->assertDontSee('Beta Directory');
});

it('deletes a promotion directory', function () {
    $directory = PromotionDirectory::factory()->create();

    $this->actingAs($this->admin);

    Livewire::test(Index::class)
        ->call('delete', $directory->id);

    $this->assertDatabaseMissing(PromotionDirectory::class, ['id' => $directory->id]);
});

// --- Create form ---

it('creates a new promotion directory', function () {
    $this->actingAs($this->admin);

    Livewire::test(Form::class)
        ->set('name', 'My Directory')
        ->set('url', 'https://example.com')
        ->set('category', 'developer')
        ->set('submission_type', 'free')
        ->call('save');

    $this->assertDatabaseHas(PromotionDirectory::class, [
        'name' => 'My Directory',
        'url' => 'https://example.com',
    ]);
});

it('validates required fields on promotion directory create', function () {
    $this->actingAs($this->admin);

    Livewire::test(Form::class)
        ->set('name', '')
        ->set('url', '')
        ->call('save')
        ->assertHasErrors(['name', 'url']);
});

// --- Edit form ---

it('loads existing directory data in edit form', function () {
    $directory = PromotionDirectory::factory()->create([
        'name' => 'Existing Directory',
        'url' => 'https://existing.com',
    ]);

    $this->actingAs($this->admin);

    Livewire::test(Form::class, ['uuid' => $directory->uuid])
        ->assertSet('name', 'Existing Directory')
        ->assertSet('url', 'https://existing.com');
});

it('updates an existing promotion directory', function () {
    $directory = PromotionDirectory::factory()->create(['name' => 'Old Name']);

    $this->actingAs($this->admin);

    Livewire::test(Form::class, ['uuid' => $directory->uuid])
        ->set('name', 'New Name')
        ->call('save');

    $this->assertDatabaseHas(PromotionDirectory::class, [
        'id' => $directory->id,
        'name' => 'New Name',
    ]);
});
