<?php

use App\Livewire\ScrappApis\Form;
use App\Livewire\ScrappApis\Index;
use App\Models\ScrappApi;
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

it('redirects unauthenticated users to login for scrapp-apis index', function () {
    $this->get('/workbench/scrapp-apis')->assertRedirect('/admin/login');
});

it('returns 403 for non-admin users on scrapp-apis index', function () {
    $this->actingAs($this->user)->get('/workbench/scrapp-apis')->assertForbidden();
});

it('returns 200 for admin users on scrapp-apis index', function () {
    $this->actingAs($this->admin)->get('/workbench/scrapp-apis')->assertOk();
});

it('returns 403 for non-admin users on create page', function () {
    $this->actingAs($this->user)->get('/workbench/scrapp-apis/create')->assertForbidden();
});

it('returns 403 for non-admin users on edit page', function () {
    $api = ScrappApi::factory()->create();
    $this->actingAs($this->user)->get("/workbench/scrapp-apis/{$api->id}/edit")->assertForbidden();
});

// --- List component ---

it('renders the index component with scrapp apis table', function () {
    $api = ScrappApi::factory()->create(['name' => 'Test API']);

    $this->actingAs($this->admin);

    Livewire::test(Index::class)
        ->assertSuccessful()
        ->assertSee('Test API');
});

it('filters apis by search term', function () {
    ScrappApi::factory()->create(['name' => 'Alpha Service']);
    ScrappApi::factory()->create(['name' => 'Beta Service']);

    $this->actingAs($this->admin);

    Livewire::test(Index::class)
        ->set('search', 'Alpha')
        ->assertSee('Alpha Service')
        ->assertDontSee('Beta Service');
});

it('deletes a scrapp api', function () {
    $api = ScrappApi::factory()->create();

    $this->actingAs($this->admin);

    Livewire::test(Index::class)
        ->call('delete', $api->id);

    $this->assertDatabaseMissing(ScrappApi::class, ['id' => $api->id]);
});

// --- Create form ---

it('creates a new scrapp api', function () {
    $this->actingAs($this->admin);

    Livewire::test(Form::class)
        ->set('name', 'My New API')
        ->set('slug', 'my-new-api')
        ->set('route_prefix', 'api/my-new-api')
        ->set('rapidapi_slug', 'my-new-api-scraper')
        ->set('is_active', true)
        ->call('save');

    $this->assertDatabaseHas(ScrappApi::class, [
        'name' => 'My New API',
        'slug' => 'my-new-api',
    ]);
});

it('validates required fields on create', function () {
    $this->actingAs($this->admin);

    Livewire::test(Form::class)
        ->set('name', '')
        ->set('slug', '')
        ->call('save')
        ->assertHasErrors(['name', 'slug']);
});

// --- Edit form ---

it('loads existing api data in edit form', function () {
    $api = ScrappApi::factory()->create([
        'name' => 'Existing API',
        'slug' => 'existing-api',
    ]);

    $this->actingAs($this->admin);

    Livewire::test(Form::class, ['id' => $api->id])
        ->assertSet('name', 'Existing API')
        ->assertSet('slug', 'existing-api');
});

it('updates an existing scrapp api', function () {
    $api = ScrappApi::factory()->create(['name' => 'Old Name']);

    $this->actingAs($this->admin);

    Livewire::test(Form::class, ['id' => $api->id])
        ->set('name', 'New Name')
        ->call('save');

    $this->assertDatabaseHas(ScrappApi::class, [
        'id' => $api->id,
        'name' => 'New Name',
    ]);
});
