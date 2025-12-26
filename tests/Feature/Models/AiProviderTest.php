<?php

use App\Models\AiProvider;

test('it can create a provider', function () {
    $provider = AiProvider::factory()->create([
        'name' => 'claude',
        'display_name' => 'Claude',
    ]);

    expect($provider->name)->toBe('claude')
        ->and($provider->display_name)->toBe('Claude')
        ->and($provider->is_active)->toBeTrue();
});

test('it encrypts api key', function () {
    $provider = AiProvider::factory()->create([
        'api_key' => 'secret-key-123',
    ]);

    $provider->refresh();

    expect($provider->api_key)->toBe('secret-key-123');
    $this->assertDatabaseMissing('ai_providers', [
        'api_key' => 'secret-key-123',
    ]);
});

test('it returns env array for glm', function () {
    $provider = AiProvider::factory()->glm()->create();

    $env = $provider->getEnvironmentVariables();

    expect($env)->toHaveKey('ANTHROPIC_BASE_URL')
        ->and($env)->toHaveKey('ANTHROPIC_AUTH_TOKEN')
        ->and($env)->toHaveKey('ANTHROPIC_MODEL');
});

test('it returns empty env array for claude', function () {
    $provider = AiProvider::factory()->claude()->create();

    $env = $provider->getEnvironmentVariables();

    expect($env)->toBeEmpty();
});

test('default scope returns first active', function () {
    AiProvider::factory()->claude()->create();
    AiProvider::factory()->glm()->create();

    $default = AiProvider::getDefault();

    expect($default->name)->toBe('claude');
});
