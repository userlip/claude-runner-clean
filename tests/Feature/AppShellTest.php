<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

test('unauthenticated GET /app redirects to login', function () {
    $this->get('/workbench')->assertRedirect('/admin/login');
});

test('authenticated GET /app returns 200', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/workbench')->assertOk();
});

test('app layout contains sidebar and main content area', function () {
    $user = User::factory()->create();

    $html = $this->actingAs($user)->get('/workbench')->getContent();

    expect($html)->toContain('drawer');
    expect($html)->toContain('main-drawer');
});

test('sidebar nav items are visible for authenticated users and link to /app paths', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/workbench')
        ->assertOk()
        ->assertSee('/workbench/tasks', false)
        ->assertSee('/workbench/repositories', false)
        ->assertSee('/workbench/personas', false)
        ->assertSee('/workbench/snippets', false);
});

test('existing /admin routes are unaffected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/admin')->assertSuccessful();
});

test('admin user sees admin nav link', function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get('/workbench')
        ->assertOk()
        ->assertSee('/admin', false);
});
