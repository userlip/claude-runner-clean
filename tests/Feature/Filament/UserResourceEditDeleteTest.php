<?php

use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\User;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $role = Role::firstOrCreate(['name' => 'super_admin']);

    $permissions = [
        'View:User',
        'ViewAny:User',
        'Create:User',
        'Update:User',
        'Delete:User',
    ];

    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission]);
    }

    $role->givePermissionTo($permissions);

    $user = User::factory()->create();
    $user->assignRole($role);

    $this->actingAs($user);
});

test('can edit user', function () {
    $user = User::factory()->create();

    Livewire::test(ListUsers::class)
        ->assertOk()
        ->callAction(TestAction::make('edit')->table($user), data: [
            'name' => 'Updated Name',
            'email' => $user->email,
        ])
        ->assertHasNoActionErrors();

    expect($user->fresh()->name)->toBe('Updated Name');
});

test('can edit user email', function () {
    $user = User::factory()->create();

    Livewire::test(ListUsers::class)
        ->callAction(TestAction::make('edit')->table($user), data: [
            'name' => $user->name,
            'email' => 'newemail@example.com',
        ])
        ->assertHasNoActionErrors();

    expect($user->fresh()->email)->toBe('newemail@example.com');
});

test('edit validates unique name', function () {
    $existingUser = User::factory()->create(['name' => 'Existing Name']);
    $userToEdit = User::factory()->create(['name' => 'Original Name']);

    Livewire::test(ListUsers::class)
        ->callAction(TestAction::make('edit')->table($userToEdit), data: [
            'name' => 'Existing Name',
            'email' => $userToEdit->email,
        ])
        ->assertHasActionErrors(['name']);

    expect($userToEdit->fresh()->name)->toBe('Original Name');
});

test('edit validates unique email', function () {
    $userToEdit = User::factory()->create(['email' => 'original@example.com']);
    $existingUser = User::factory()->create(['email' => 'existing@example.com']);

    Livewire::test(ListUsers::class)
        ->callAction(TestAction::make('edit')->table($userToEdit), data: [
            'name' => $userToEdit->name,
            'email' => 'existing@example.com',
        ])
        ->assertHasActionErrors(['email']);

    expect($userToEdit->fresh()->email)->toBe('original@example.com');
});

test('can delete user', function () {
    $user = User::factory()->create();

    Livewire::test(ListUsers::class)
        ->callAction(TestAction::make('delete')->table($user))
        ->assertHasNoActionErrors();

    expect(User::find($user->id))->toBeNull();
});

test('can bulk delete users', function () {
    $users = User::factory()->count(3)->create();

    Livewire::test(ListUsers::class)
        ->selectTableRecords($users)
        ->callAction(TestAction::make(DeleteBulkAction::class)->table()->bulk())
        ->assertHasNoActionErrors();

    foreach ($users as $user) {
        expect(User::find($user->id))->toBeNull();
    }
});
