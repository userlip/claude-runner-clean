<?php

namespace Tests\Feature;

use App\Models\Repository;
use App\Models\Task;
use App\Services\SecurityManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SecurityManagementAiPromptTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_or_reuses_security_task(): void
    {
        $repo = Repository::factory()->create(['security_management_enabled' => true]);
        File::shouldReceive('exists')->andReturn(true);
        File::shouldReceive('get')->andReturn('PROMPT');

        app(SecurityManagementService::class)->ensureSecurityTask($repo);

        $this->assertNotNull($repo->fresh()->security_task_id);
        $this->assertInstanceOf(Task::class, $repo->fresh()->securityTask);
    }
}
