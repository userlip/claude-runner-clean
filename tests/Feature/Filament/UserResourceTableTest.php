<?php

use App\Filament\Resources\UserResource;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Permission::firstOrCreate(['name' => 'ViewAny:User']);

    $user = User::factory()->create();
    $user->givePermissionTo('ViewAny:User');

    $this->actingAs($user);
});

test('can render user list page', function () {
    Livewire::test(ListUsers::class)
        ->assertSuccessful();
});

test('can see users in table', function () {
    $users = User::factory()->count(3)->create();

    Livewire::test(ListUsers::class)
        ->assertCanSeeTableRecords($users);
});

test('can search users by name', function () {
    $users = User::factory()->count(3)->create();
    $targetUser = $users->first();

    Livewire::test(ListUsers::class)
        ->assertCanSeeTableRecords($users)
        ->searchTable($targetUser->name)
        ->assertCanSeeTableRecords([$targetUser])
        ->assertCanNotSeeTableRecords($users->skip(1));
});

test('can search users by email', function () {
    $users = User::factory()->count(3)->create();
    $targetUser = $users->last();

    Livewire::test(ListUsers::class)
        ->assertCanSeeTableRecords($users)
        ->searchTable($targetUser->email)
        ->assertCanSeeTableRecords([$targetUser])
        ->assertCanNotSeeTableRecords($users->take($users->count() - 1));
});

test('can sort users by created_at', function () {
    $newestUser = User::factory()->create([
        'created_at' => now(),
    ]);

    $oldestUser = User::factory()->create([
        'created_at' => now()->subDays(10),
    ]);

    Livewire::test(ListUsers::class)
        ->sortTable('created_at')
        ->assertCanSeeTableRecords([$oldestUser, $newestUser], inOrder: true)
        ->sortTable('created_at', 'desc')
        ->assertCanSeeTableRecords([$newestUser, $oldestUser], inOrder: true);
});

test('shows navigation badge with user count', function () {
    $existingCount = User::count();
    User::factory()->count(5)->create();

    $badge = UserResource::getNavigationBadge();

    expect($badge)->toBe(number_format($existingCount + 5));
});
