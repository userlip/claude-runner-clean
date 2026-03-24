<?php

use App\Jobs\TestMcpServerConnectionJob;
use App\Models\McpServer;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->exportDir = storage_path('framework/testing/mcp-tester');
    $this->exportPath = "{$this->exportDir}/.mcp.json";

    config()->set('mcp.export_path', $this->exportPath);
    config()->set('mcp.managed_state_path', "{$this->exportDir}/managed-state.json");

    File::deleteDirectory($this->exportDir);
    File::ensureDirectoryExists($this->exportDir);
});

afterEach(function () {
    File::deleteDirectory($this->exportDir);
});

test('connection tester succeeds for a command transport', function () {
    expect(class_exists(\App\Services\McpConnectionTester::class))->toBeTrue();

    Process::fake([
        '*' => Process::result(output: 'MCP ready', exitCode: 0),
    ]);

    $server = McpServer::factory()->command()->create([
        'name' => 'filesystem',
        'command' => 'npx',
        'args' => ['-y', '@modelcontextprotocol/server-filesystem', '/srv/app'],
    ]);

    $tester = app(\App\Services\McpConnectionTester::class);
    $result = $tester->test($server);

    expect($result['successful'])->toBeTrue()
        ->and($result['status'])->toBe('success')
        ->and($result['message'])->toContain('MCP ready');
});

test('connection tester treats a long-running command transport as reachable', function () {
    expect(class_exists(\App\Services\McpConnectionTester::class))->toBeTrue();

    Process::fake([
        '*' => Process::describe()
            ->output('MCP server booting')
            ->iterations(3)
            ->exitCode(0),
    ]);

    $server = McpServer::factory()->command()->create([
        'name' => 'filesystem',
        'command' => 'npx',
        'args' => ['-y', '@modelcontextprotocol/server-filesystem', '/srv/app'],
    ]);

    $tester = app(\App\Services\McpConnectionTester::class);
    $result = $tester->test($server);

    expect($result['successful'])->toBeTrue()
        ->and($result['status'])->toBe('success')
        ->and($result['message'])->toContain('reachable');
});

test('connection tester fails for a non-zero command transport result', function () {
    expect(class_exists(\App\Services\McpConnectionTester::class))->toBeTrue();

    Process::fake([
        '*' => Process::result(output: '', errorOutput: 'spawn failed', exitCode: 1),
    ]);

    $server = McpServer::factory()->command()->create();

    $tester = app(\App\Services\McpConnectionTester::class);
    $result = $tester->test($server);

    expect($result['successful'])->toBeFalse()
        ->and($result['status'])->toBe('failed')
        ->and($result['message'])->toContain('spawn failed');
});

test('connection tester passes command transport environment variables to the process', function () {
    expect(class_exists(\App\Services\McpConnectionTester::class))->toBeTrue();

    Process::fake([
        '*' => Process::result(output: 'MCP ready', exitCode: 0),
    ]);

    $server = McpServer::factory()->command()->create([
        'env_vars' => [
            'MCP_TOKEN' => 'secret-token',
            'ROOT_PATH' => '/srv/app',
        ],
    ]);

    $tester = app(\App\Services\McpConnectionTester::class);
    $result = $tester->test($server);

    expect($result['successful'])->toBeTrue();

    Process::assertRan(function (\Illuminate\Process\PendingProcess $process): bool {
        return $process->environment === [
            'MCP_TOKEN' => 'secret-token',
            'ROOT_PATH' => '/srv/app',
        ];
    });
});

test('connection tester succeeds for an sse transport', function () {
    expect(class_exists(\App\Services\McpConnectionTester::class))->toBeTrue();

    Http::fake([
        'https://docs.example.com/mcp' => Http::response('ok', 200),
    ]);

    $server = McpServer::factory()->sse()->create([
        'url' => 'https://docs.example.com/mcp',
        'headers' => ['Authorization' => 'Bearer abc123'],
    ]);

    $tester = app(\App\Services\McpConnectionTester::class);
    $result = $tester->test($server);

    expect($result['successful'])->toBeTrue()
        ->and($result['status'])->toBe('success')
        ->and($result['message'])->toContain('200');
});

test('connection tester fails for an unreachable sse transport', function () {
    expect(class_exists(\App\Services\McpConnectionTester::class))->toBeTrue();

    Http::fake([
        'https://docs.example.com/mcp' => Http::response('server error', 500),
    ]);

    $server = McpServer::factory()->sse()->create([
        'url' => 'https://docs.example.com/mcp',
    ]);

    $tester = app(\App\Services\McpConnectionTester::class);
    $result = $tester->test($server);

    expect($result['successful'])->toBeFalse()
        ->and($result['status'])->toBe('failed')
        ->and($result['message'])->toContain('500');
});

test('connection tester can queue a server test job', function () {
    expect(class_exists(\App\Services\McpConnectionTester::class))->toBeTrue();

    Queue::fake();

    $server = McpServer::factory()->create();

    $tester = app(\App\Services\McpConnectionTester::class);
    $tester->queue($server);

    Queue::assertPushed(TestMcpServerConnectionJob::class, function (TestMcpServerConnectionJob $job) use ($server) {
        return $job->mcpServer->is($server);
    });
});
