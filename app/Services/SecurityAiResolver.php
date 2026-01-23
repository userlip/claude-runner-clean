<?php

namespace App\Services;

use App\Models\AiProvider;

class SecurityAiResolver
{
    public function orchestratorProvider(): ?AiProvider
    {
        $id = config('services.security_ai.orchestrator_provider_id');

        return $id ? AiProvider::find($id) : AiProvider::getDefault();
    }

    public function fixerProvider(): ?AiProvider
    {
        $id = config('services.security_ai.fixer_provider_id');

        return $id ? AiProvider::find($id) : AiProvider::getDefault();
    }
}
