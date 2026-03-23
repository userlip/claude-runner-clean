<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

it('shows the Admin nav link to admin users', function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get('/workbench')
        ->assertStatus(200)
        ->assertSee('Admin', false)
        ->assertSee('/admin', false);
});

it('does not show the Admin nav link to non-admin users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/workbench')
        ->assertStatus(200)
        ->assertDontSee('o-cog-6-tooth', false);
});
