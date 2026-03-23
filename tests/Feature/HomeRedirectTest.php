<?php

use App\Models\User;

test('GET / redirects to /app', function () {
    $response = $this->get('/');

    $response->assertRedirect('/workbench');
});

test('GET / redirects authenticated users to /app', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/');

    $response->assertRedirect('/workbench');
});

test('GET /app redirects unauthenticated users to login', function () {
    $response = $this->get('/workbench');

    $response->assertRedirect('/admin/login');
});

test('GET /app returns 200 for authenticated users', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/workbench');

    $response->assertOk();
});

test('GET /admin still works for filament users', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/admin');

    $response->assertSuccessful();
});
