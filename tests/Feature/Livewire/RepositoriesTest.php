<?php

use App\Livewire\Repositories\Form;
use App\Livewire\Repositories\Index;
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

it('redirects unauthenticated users to login for repositories index', function () {
    $this->get('/workbench/repositories')->assertRedirect('/admin/login');
});

it('returns 403 for non-admin users on repositories index', function () {
    $this->actingAs($this->user)->get('/workbench/repositories')->assertForbidden();
});

it('returns 200 for admin users on repositories index', function () {
    $this->actingAs($this->admin)->get('/workbench/repositories')->assertOk();
});

it('returns 403 for non-admin users on repositories create page', function () {
    $this->actingAs($this->user)->get('/workbench/repositories/create')->assertForbidden();
});

it('returns 403 for non-admin users on repositories edit page', function () {
    $repository = Repository::factory()->create();
    $this->actingAs($this->user)->get("/workbench/repositories/{$repository->id}/edit")->assertForbidden();
});

// --- List component ---

it('renders the repositories index with repositories table', function () {
    $repository = Repository::factory()->create(['name' => 'test-repo', 'full_name' => 'owner/test-repo']);

    $this->actingAs($this->admin);

    Livewire::test(Index::class)
        ->assertSuccessful()
        ->assertSee('owner/test-repo');
});

it('filters repositories by search term', function () {
    Repository::factory()->create(['name' => 'alpha-repo', 'full_name' => 'owner/alpha-repo']);
    Repository::factory()->create(['name' => 'beta-repo', 'full_name' => 'owner/beta-repo']);

    $this->actingAs($this->admin);

    Livewire::test(Index::class)
        ->set('search', 'alpha')
        ->assertSee('owner/alpha-repo')
        ->assertDontSee('owner/beta-repo');
});

it('deletes a repository', function () {
    $repository = Repository::factory()->create();

    $this->actingAs($this->admin);

    Livewire::test(Index::class)
        ->call('delete', $repository->id);

    $this->assertDatabaseMissing(Repository::class, ['id' => $repository->id]);
});

// --- Create form ---

it('creates a new repository', function () {
    $this->actingAs($this->admin);

    Livewire::test(Form::class)
        ->set('name', 'my-new-repo')
        ->set('fullName', 'owner/my-new-repo')
        ->set('defaultBranch', 'main')
        ->set('githubId', 12345678)
        ->call('save');

    $this->assertDatabaseHas(Repository::class, [
        'name' => 'my-new-repo',
        'full_name' => 'owner/my-new-repo',
        'github_id' => 12345678,
    ]);
});

it('validates required fields on repository create', function () {
    $this->actingAs($this->admin);

    Livewire::test(Form::class)
        ->set('name', '')
        ->call('save')
        ->assertHasErrors(['name']);
});

it('sets user_id to authenticated user on create', function () {
    $this->actingAs($this->admin);

    Livewire::test(Form::class)
        ->set('name', 'owned-repo')
        ->set('githubId', 87654321)
        ->call('save');

    $this->assertDatabaseHas(Repository::class, [
        'name' => 'owned-repo',
        'user_id' => $this->admin->id,
    ]);
});

// --- Edit form ---

it('loads existing repository data in edit form', function () {
    $repository = Repository::factory()->create([
        'name' => 'existing-repo',
        'full_name' => 'owner/existing-repo',
        'default_branch' => 'develop',
    ]);

    $this->actingAs($this->admin);

    Livewire::test(Form::class, ['id' => $repository->id])
        ->assertSet('name', 'existing-repo')
        ->assertSet('fullName', 'owner/existing-repo')
        ->assertSet('defaultBranch', 'develop');
});

it('updates an existing repository', function () {
    $repository = Repository::factory()->create(['name' => 'old-name']);

    $this->actingAs($this->admin);

    Livewire::test(Form::class, ['id' => $repository->id])
        ->set('name', 'new-name')
        ->call('save');

    $this->assertDatabaseHas(Repository::class, [
        'id' => $repository->id,
        'name' => 'new-name',
    ]);
});
