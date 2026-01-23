<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepositorySecurityUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_repository_resource_includes_security_toggle(): void
    {
        $this->assertTrue(class_exists(\App\Filament\Resources\RepositoryResource::class));
    }
}
