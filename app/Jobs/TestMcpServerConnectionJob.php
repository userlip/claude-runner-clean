<?php

namespace App\Jobs;

use App\Models\McpServer;
use App\Services\McpConnectionTester;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;

class TestMcpServerConnectionJob implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(public McpServer $mcpServer) {}

    public function handle(McpConnectionTester $tester): void
    {
        $server = $this->mcpServer->fresh();

        if (! $server) {
            return;
        }

        $result = $tester->test($server);

        $server->forceFill([
            'last_tested_at' => now(),
            'last_test_status' => $result['status'],
            'last_test_message' => $result['message'],
        ])->save();
    }
}
