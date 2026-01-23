<?php

namespace Tests\Unit;

use App\Models\Repository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepositorySecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_management_enabled_casts_to_bool(): void
    {
        $repo = Repository::factory()->create(['security_management_enabled' => 1]);

        $this->assertTrue($repo->security_management_enabled);
    }
}
