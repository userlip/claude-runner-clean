<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

it('shows the Admin nav link to admin users', function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get('/app')
        ->assertStatus(200)
        ->assertSee('admin-nav-link', false)
        ->assertSee('/admin', false);
});

it('does not show the Admin nav link to non-admin users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/app')
        ->assertStatus(200)
        ->assertDontSee('admin-nav-link', false);
});
