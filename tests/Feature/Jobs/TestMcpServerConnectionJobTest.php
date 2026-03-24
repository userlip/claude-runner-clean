<?php

use App\Jobs\TestMcpServerConnectionJob;
use App\Models\McpServer;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

beforeEach(function () {
    $this->exportDir = storage_path('framework/testing/mcp-job');
    $this->exportPath = "{$this->exportDir}/.mcp.json";

    config()->set('mcp.export_path', $this->exportPath);
    config()->set('mcp.managed_state_path', "{$this->exportDir}/managed-state.json");

    File::deleteDirectory($this->exportDir);
    File::ensureDirectoryExists($this->exportDir);
});

afterEach(function () {
    File::deleteDirectory($this->exportDir);
});

test('connection test job updates success metadata on the model', function () {
    expect(class_exists(TestMcpServerConnectionJob::class))->toBeTrue();

    Process::fake([
        '*' => Process::result(output: 'MCP ready', exitCode: 0),
    ]);

    $server = McpServer::factory()->command()->create([
        'last_tested_at' => null,
        'last_test_status' => null,
        'last_test_message' => null,
    ]);

    $job = new TestMcpServerConnectionJob($server);
    $job->handle(app(\App\Services\McpConnectionTester::class));

    $server->refresh();

    expect($server->last_tested_at)->not->toBeNull()
        ->and($server->last_test_status)->toBe('success')
        ->and($server->last_test_message)->toContain('MCP ready');
});

test('connection test job updates failed metadata on the model', function () {
    expect(class_exists(TestMcpServerConnectionJob::class))->toBeTrue();

    Http::fake([
        'https://docs.example.com/mcp' => Http::response('unauthorized', 401),
    ]);

    $server = McpServer::factory()->sse()->create([
        'url' => 'https://docs.example.com/mcp',
        'last_tested_at' => null,
        'last_test_status' => null,
        'last_test_message' => null,
    ]);

    $job = new TestMcpServerConnectionJob($server);
    $job->handle(app(\App\Services\McpConnectionTester::class));

    $server->refresh();

    expect($server->last_tested_at)->not->toBeNull()
        ->and($server->last_test_status)->toBe('failed')
        ->and($server->last_test_message)->toContain('401');
});

test('connection test job exits cleanly if the server was deleted before execution', function () {
    expect(class_exists(TestMcpServerConnectionJob::class))->toBeTrue();

    $server = McpServer::factory()->command()->create();
    $job = new TestMcpServerConnectionJob($server);

    $server->delete();

    $job->handle(app(\App\Services\McpConnectionTester::class));

    expect(McpServer::query()->whereKey($server->getKey())->exists())->toBeFalse();
});
