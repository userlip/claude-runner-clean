<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

test('unauthenticated GET /app redirects to login', function () {
    $this->get('/app')->assertRedirect('/admin/login');
});

test('authenticated GET /app returns 200', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/app')->assertOk();
});

test('app layout contains sidebar and main content area', function () {
    $user = User::factory()->create();

    $html = $this->actingAs($user)->get('/app')->getContent();

    expect($html)->toContain('id="app-sidebar"');
    expect($html)->toContain('<main');
});

test('sidebar nav items are visible for authenticated users and link to /app paths', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/app')
        ->assertOk()
        ->assertSee('id="app-nav-items"', false)
        ->assertSee('/app/tasks', false)
        ->assertSee('/app/repositories', false)
        ->assertSee('/app/personas', false)
        ->assertSee('/app/snippets', false);
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
        ->get('/app')
        ->assertOk()
        ->assertSee('admin-nav-link', false);
});
