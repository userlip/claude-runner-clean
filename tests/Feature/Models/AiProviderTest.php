<?php

namespace Tests\Feature\Models;

use App\Models\AiProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_create_a_provider(): void
    {
        $provider = AiProvider::factory()->create([
            'name' => 'claude',
            'display_name' => 'Claude',
        ]);

        expect($provider->name)->toBe('claude')
            ->and($provider->display_name)->toBe('Claude')
            ->and($provider->is_active)->toBeTrue();
    }

    public function test_it_encrypts_api_key(): void
    {
        $provider = AiProvider::factory()->create([
            'api_key' => 'secret-key-123',
        ]);

        $provider->refresh();

        expect($provider->api_key)->toBe('secret-key-123');
        $this->assertDatabaseMissing('ai_providers', [
            'api_key' => 'secret-key-123',
        ]);
    }

    public function test_it_returns_env_array_for_glm(): void
    {
        $provider = AiProvider::factory()->glm()->create();

        $env = $provider->getEnvironmentVariables();

        expect($env)->toHaveKey('ANTHROPIC_BASE_URL')
            ->and($env)->toHaveKey('ANTHROPIC_AUTH_TOKEN')
            ->and($env)->toHaveKey('ANTHROPIC_MODEL');
    }

    public function test_it_returns_empty_env_array_for_claude(): void
    {
        $provider = AiProvider::factory()->claude()->create();

        $env = $provider->getEnvironmentVariables();

        expect($env)->toBeEmpty();
    }

    public function test_default_scope_returns_first_active(): void
    {
        AiProvider::factory()->claude()->create();
        AiProvider::factory()->glm()->create();

        $default = AiProvider::getDefault();

        expect($default->name)->toBe('claude');
    }
}
