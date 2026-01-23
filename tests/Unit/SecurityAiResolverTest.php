<?php

namespace Tests\Unit;

use App\Models\AiProvider;
use App\Services\SecurityAiResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class SecurityAiResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_orchestrator_provider_uses_configured_id(): void
    {
        $provider = AiProvider::factory()->create(['is_default' => false]);
        Config::set('services.security_ai.orchestrator_provider_id', $provider->id);

        $resolved = app(SecurityAiResolver::class)->orchestratorProvider();

        $this->assertSame($provider->id, $resolved?->id);
    }
}
