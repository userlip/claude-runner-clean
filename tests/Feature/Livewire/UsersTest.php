<?php

use App\Livewire\Users\Form;
use App\Livewire\Users\Index;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->user = User::factory()->create();
});

// --- Access control ---

it('redirects unauthenticated users to login for users index', function () {
    $this->get('/workbench/users')->assertRedirect('/admin/login');
});

it('returns 403 for non-admin users on users index', function () {
    $this->actingAs($this->user)->get('/workbench/users')->assertForbidden();
});

it('returns 200 for admin users on users index', function () {
    $this->actingAs($this->admin)->get('/workbench/users')->assertOk();
});

it('returns 403 for non-admin users on users create page', function () {
    $this->actingAs($this->user)->get('/workbench/users/create')->assertForbidden();
});

it('returns 403 for non-admin users on users edit page', function () {
    $this->actingAs($this->user)->get("/workbench/users/{$this->admin->id}/edit")->assertForbidden();
});

// --- List component ---

it('renders the users index with users table', function () {
    $this->actingAs($this->admin);

    Livewire::test(Index::class)
        ->assertSuccessful()
        ->assertSee($this->admin->name);
});

it('filters users by search term', function () {
    $alpha = User::factory()->create(['name' => 'Alice Alpha', 'email' => 'alice@example.com']);
    $beta = User::factory()->create(['name' => 'Bob Beta', 'email' => 'bob@example.com']);

    $this->actingAs($this->admin);

    Livewire::test(Index::class)
        ->set('search', 'Alice')
        ->assertSee('Alice Alpha')
        ->assertDontSee('Bob Beta');
});

it('filters users by email search term', function () {
    $alpha = User::factory()->create(['name' => 'Alice Search', 'email' => 'uniquealice@example.com']);
    $beta = User::factory()->create(['name' => 'Bob Search', 'email' => 'uniquebob@example.com']);

    $this->actingAs($this->admin);

    Livewire::test(Index::class)
        ->set('search', 'uniquealice')
        ->assertSee('uniquealice@example.com')
        ->assertDontSee('uniquebob@example.com');
});

it('deletes a user', function () {
    $target = User::factory()->create();

    $this->actingAs($this->admin);

    Livewire::test(Index::class)
        ->call('delete', $target->id);

    $this->assertDatabaseMissing(User::class, ['id' => $target->id]);
});

// --- Create form ---

it('creates a new user', function () {
    $this->actingAs($this->admin);

    Livewire::test(Form::class)
        ->set('name', 'New User Name')
        ->set('email', 'newuser@example.com')
        ->set('password', 'secret123')
        ->set('passwordConfirmation', 'secret123')
        ->call('save');

    $this->assertDatabaseHas(User::class, [
        'name' => 'New User Name',
        'email' => 'newuser@example.com',
    ]);
});

it('validates required fields on user create', function () {
    $this->actingAs($this->admin);

    Livewire::test(Form::class)
        ->set('name', '')
        ->set('email', '')
        ->call('save')
        ->assertHasErrors(['name', 'email', 'password']);
});

it('validates password confirmation on create', function () {
    $this->actingAs($this->admin);

    Livewire::test(Form::class)
        ->set('name', 'Test')
        ->set('email', 'test@example.com')
        ->set('password', 'secret123')
        ->set('passwordConfirmation', 'different')
        ->call('save')
        ->assertHasErrors(['password']);
});

// --- Edit form ---

it('loads existing user data in edit form', function () {
    $target = User::factory()->create(['name' => 'Existing User', 'email' => 'existing@example.com']);

    $this->actingAs($this->admin);

    Livewire::test(Form::class, ['id' => $target->id])
        ->assertSet('name', 'Existing User')
        ->assertSet('email', 'existing@example.com');
});

it('updates an existing user name and email', function () {
    $target = User::factory()->create(['name' => 'Old Name', 'email' => 'old@example.com']);

    $this->actingAs($this->admin);

    Livewire::test(Form::class, ['id' => $target->id])
        ->set('name', 'New Name')
        ->set('email', 'new@example.com')
        ->call('save');

    $this->assertDatabaseHas(User::class, [
        'id' => $target->id,
        'name' => 'New Name',
        'email' => 'new@example.com',
    ]);
});

it('does not reset password when left blank on edit', function () {
    $target = User::factory()->create(['password' => Hash::make('original_password')]);
    $originalHash = $target->password;

    $this->actingAs($this->admin);

    Livewire::test(Form::class, ['id' => $target->id])
        ->set('name', 'Updated Name')
        ->set('email', $target->email)
        ->set('password', '')
        ->call('save');

    expect($target->fresh()->password)->toBe($originalHash);
});

it('updates password when provided on edit', function () {
    $target = User::factory()->create(['password' => Hash::make('old_password')]);

    $this->actingAs($this->admin);

    Livewire::test(Form::class, ['id' => $target->id])
        ->set('name', $target->name)
        ->set('email', $target->email)
        ->set('password', 'new_password123')
        ->set('passwordConfirmation', 'new_password123')
        ->call('save');

    expect(Hash::check('new_password123', $target->fresh()->password))->toBeTrue();
});
