<?php

namespace Tests\Feature;

use App\Jobs\RunSecurityManagementJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SecurityCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_command_dispatches_job(): void
    {
        Queue::fake();

        $this->artisan('security:orchestrate')->assertExitCode(0);

        Queue::assertPushed(RunSecurityManagementJob::class);
    }
}
