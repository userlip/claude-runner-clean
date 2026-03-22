<?php

use App\Livewire\Sites\Form;
use App\Livewire\Sites\Index;
use App\Models\Repository;
use App\Models\Site;
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

it('redirects unauthenticated users to login for sites index', function () {
    $this->get('/app/sites')->assertRedirect('/admin/login');
});

it('returns 403 for non-admin users on sites index', function () {
    $this->actingAs($this->user)->get('/app/sites')->assertForbidden();
});

it('returns 200 for admin users on sites index', function () {
    $this->actingAs($this->admin)->get('/app/sites')->assertOk();
});

it('returns 403 for non-admin users on sites create page', function () {
    $this->actingAs($this->user)->get('/app/sites/create')->assertForbidden();
});

it('returns 403 for non-admin users on sites edit page', function () {
    $site = Site::factory()->create();
    $this->actingAs($this->user)->get("/app/sites/{$site->id}/edit")->assertForbidden();
});

// --- List component ---

it('renders the sites index with sites table', function () {
    $site = Site::factory()->create(['domain' => 'test.marin.sh']);

    $this->actingAs($this->admin);

    Livewire::test(Index::class)
        ->assertSuccessful()
        ->assertSee('test.marin.sh');
});

it('filters sites by search term', function () {
    Site::factory()->create(['domain' => 'alpha.marin.sh']);
    Site::factory()->create(['domain' => 'beta.marin.sh']);

    $this->actingAs($this->admin);

    Livewire::test(Index::class)
        ->set('search', 'alpha')
        ->assertSee('alpha.marin.sh')
        ->assertDontSee('beta.marin.sh');
});

it('deletes a site', function () {
    $site = Site::factory()->create();

    $this->actingAs($this->admin);

    Livewire::test(Index::class)
        ->call('delete', $site->id);

    $this->assertDatabaseMissing(Site::class, ['id' => $site->id]);
});

// --- Create form ---

it('creates a new site', function () {
    $this->actingAs($this->admin);

    Livewire::test(Form::class)
        ->set('domain', 'newsite.marin.sh')
        ->set('branch', 'main')
        ->set('phpVersion', '8.4')
        ->set('status', 'pending')
        ->call('save');

    $this->assertDatabaseHas(Site::class, [
        'domain' => 'newsite.marin.sh',
        'branch' => 'main',
    ]);
});

it('validates required fields on site create', function () {
    $this->actingAs($this->admin);

    Livewire::test(Form::class)
        ->set('domain', '')
        ->call('save')
        ->assertHasErrors(['domain']);
});

// --- Edit form ---

it('loads existing site data in edit form', function () {
    $site = Site::factory()->create([
        'domain' => 'edit.marin.sh',
        'branch' => 'develop',
    ]);

    $this->actingAs($this->admin);

    Livewire::test(Form::class, ['id' => $site->id])
        ->assertSet('domain', 'edit.marin.sh')
        ->assertSet('branch', 'develop');
});

it('updates an existing site', function () {
    $site = Site::factory()->create(['domain' => 'old.marin.sh']);

    $this->actingAs($this->admin);

    Livewire::test(Form::class, ['id' => $site->id])
        ->set('domain', 'new.marin.sh')
        ->call('save');

    $this->assertDatabaseHas(Site::class, [
        'id' => $site->id,
        'domain' => 'new.marin.sh',
    ]);
});

it('accepts a repository_id on site create', function () {
    $repository = Repository::factory()->create();

    $this->actingAs($this->admin);

    Livewire::test(Form::class)
        ->set('domain', 'withrepo.marin.sh')
        ->set('repositoryId', $repository->id)
        ->set('status', 'pending')
        ->call('save');

    $this->assertDatabaseHas(Site::class, [
        'domain' => 'withrepo.marin.sh',
        'repository_id' => $repository->id,
    ]);
});
