<?php

use App\Models\User;

test('auth check returns 401 for guests', function () {
    $this->get('/ide-auth-check')
        ->assertUnauthorized();
});

test('auth check returns 200 for authenticated users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/ide-auth-check')
        ->assertOk();
});
