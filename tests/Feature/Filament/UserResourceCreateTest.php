<?php

use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Permission::firstOrCreate(['name' => 'ViewAny:User']);
    Permission::firstOrCreate(['name' => 'Create:User']);

    $user = User::factory()->create();
    $user->givePermissionTo(['ViewAny:User', 'Create:User']);

    $this->actingAs($user);
});

test('can create user', function () {
    livewire(ListUsers::class)
        ->assertSuccessful()
        ->callAction(TestAction::make('create')->table(), data: [
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
        ->assertHasNoActionErrors();

    assertDatabaseHas(User::class, [
        'name' => 'New User',
        'email' => 'new@example.com',
    ]);
});

test('validates required fields', function () {
    livewire(ListUsers::class)
        ->callAction(TestAction::make('create')->table(), data: [])
        ->assertHasActionErrors([
            'name' => 'required',
            'email' => 'required',
            'password' => 'required',
            'password_confirmation' => 'required',
        ]);

    expect(User::count())->toBe(1);
});

test('validates unique name', function () {
    $existingUser = User::factory()->create(['name' => 'Existing User']);

    livewire(ListUsers::class)
        ->callAction(TestAction::make('create')->table(), data: [
            'name' => 'Existing User',
            'email' => 'new@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
        ->assertHasActionErrors(['name' => 'unique']);

    expect(User::where('email', 'new@example.com')->exists())->toBeFalse();
});

test('validates unique email', function () {
    $existingUser = User::factory()->create(['email' => 'existing@example.com']);

    livewire(ListUsers::class)
        ->callAction(TestAction::make('create')->table(), data: [
            'name' => 'New User',
            'email' => 'existing@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
        ->assertHasActionErrors(['email' => 'unique']);

    expect(User::where('name', 'New User')->exists())->toBeFalse();
});

test('validates email format', function () {
    livewire(ListUsers::class)
        ->callAction(TestAction::make('create')->table(), data: [
            'name' => 'New User',
            'email' => 'not-an-email',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
        ->assertHasActionErrors(['email' => 'email']);

    expect(User::where('name', 'New User')->exists())->toBeFalse();
});

test('validates password confirmation', function () {
    livewire(ListUsers::class)
        ->callAction(TestAction::make('create')->table(), data: [
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different-password',
        ])
        ->assertHasActionErrors(['password' => 'confirmed']);

    expect(User::where('email', 'new@example.com')->exists())->toBeFalse();
});
