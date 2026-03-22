<?php

use App\Models\User;

test('GET /app-manifest.json returns 200 with valid JSON', function () {
    $response = $this->get('/app-manifest.json');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/manifest+json');
});

test('app manifest has correct start_url pointing to /app', function () {
    $response = $this->get('/app-manifest.json');
    $manifest = $response->json();

    expect($manifest['start_url'])->toBe('/app');
});

test('app manifest has standalone display mode', function () {
    $response = $this->get('/app-manifest.json');
    $manifest = $response->json();

    expect($manifest['display'])->toBe('standalone');
});

test('app manifest includes icons with src and sizes', function () {
    $response = $this->get('/app-manifest.json');
    $manifest = $response->json();

    expect($manifest['icons'])->not->toBeEmpty();

    foreach ($manifest['icons'] as $icon) {
        expect($icon)->toHaveKeys(['src', 'sizes', 'type']);
    }
});

test('/app layout includes manifest link tag', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/app');

    $response->assertOk();
    $response->assertSee('rel="manifest"', false);
    $response->assertSee('/app-manifest.json', false);
});

test('/app layout includes theme-color meta tag', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/app');

    $response->assertOk();
    $response->assertSee('name="theme-color"', false);
});

test('/app layout includes service worker registration script', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/app');

    $response->assertOk();
    $response->assertSee('serviceWorker', false);
    $response->assertSee('serviceworker.js', false);
});
