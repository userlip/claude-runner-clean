<?php

use App\Models\McpServer;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->exportDir = storage_path('framework/testing/mcp-exporter');
    $this->exportPath = "{$this->exportDir}/.mcp.json";

    config()->set('mcp.export_path', $this->exportPath);
    config()->set('mcp.managed_state_path', "{$this->exportDir}/managed-state.json");

    File::deleteDirectory($this->exportDir);
    File::ensureDirectoryExists($this->exportDir);
});

afterEach(function () {
    File::deleteDirectory($this->exportDir);
});

test('exporter writes only enabled servers in claude code format', function () {
    expect(class_exists(\App\Services\McpServerExporter::class))->toBeTrue();

    McpServer::factory()->command()->create([
        'name' => 'filesystem',
        'enabled' => true,
        'command' => 'npx',
        'args' => ['-y', '@modelcontextprotocol/server-filesystem', '/srv/app'],
        'env_vars' => ['ROOT_PATH' => '/srv/app'],
    ]);

    McpServer::factory()->sse()->create([
        'name' => 'remote-docs',
        'enabled' => true,
        'url' => 'https://docs.example.com/mcp',
        'headers' => ['Authorization' => 'Bearer abc123'],
    ]);

    McpServer::factory()->command()->create([
        'name' => 'disabled-server',
        'enabled' => false,
    ]);

    $exporter = app(\App\Services\McpServerExporter::class);
    $exporter->export($this->exportPath);

    expect(File::exists($this->exportPath))->toBeTrue();

    $config = json_decode(File::get($this->exportPath), true);

    expect($config)->toHaveKey('mcpServers')
        ->and($config['mcpServers'])->toHaveKeys(['filesystem', 'remote-docs'])
        ->and($config['mcpServers'])->not->toHaveKey('disabled-server')
        ->and($config['mcpServers']['filesystem'])->toMatchArray([
            'type' => 'stdio',
            'command' => 'npx',
            'args' => ['-y', '@modelcontextprotocol/server-filesystem', '/srv/app'],
            'env' => ['ROOT_PATH' => '/srv/app'],
        ])
        ->and($config['mcpServers']['remote-docs'])->toMatchArray([
            'type' => 'sse',
            'url' => 'https://docs.example.com/mcp',
            'headers' => ['Authorization' => 'Bearer abc123'],
        ]);
});

test('exporter creates parent directory and locks down file permissions', function () {
    expect(class_exists(\App\Services\McpServerExporter::class))->toBeTrue();

    McpServer::factory()->command()->create([
        'name' => 'filesystem',
        'enabled' => true,
    ]);

    File::deleteDirectory($this->exportDir);

    $exporter = app(\App\Services\McpServerExporter::class);
    $exporter->export($this->exportPath);

    expect(File::isDirectory($this->exportDir))->toBeTrue()
        ->and(File::exists($this->exportPath))->toBeTrue()
        ->and(fileperms($this->exportPath) & 0777)->toBe(0600);
});

test('exporter preserves existing claude settings when writing claude json', function () {
    expect(class_exists(\App\Services\McpServerExporter::class))->toBeTrue();

    $claudeJsonPath = "{$this->exportDir}/.claude.json";

    File::put($claudeJsonPath, json_encode([
        'theme' => 'dark',
        'editor' => ['vimMode' => true],
        'mcpServers' => [
            'old-server' => ['type' => 'stdio', 'command' => 'old'],
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    McpServer::factory()->command()->create([
        'name' => 'filesystem',
        'enabled' => true,
    ]);

    $exporter = app(\App\Services\McpServerExporter::class);
    $exporter->export($claudeJsonPath);

    $config = json_decode(File::get($claudeJsonPath), true);

    expect($config['theme'])->toBe('dark')
        ->and($config['editor'])->toBe(['vimMode' => true])
        ->and($config['mcpServers'])->toHaveKey('filesystem')
        ->and($config['mcpServers'])->toHaveKey('old-server');
});

test('exporter removes previously managed servers that are no longer enabled while preserving unmanaged entries', function () {
    expect(class_exists(\App\Services\McpServerExporter::class))->toBeTrue();

    $claudeJsonPath = "{$this->exportDir}/.claude.json";

    File::put($claudeJsonPath, json_encode([
        'theme' => 'dark',
        'mcpServers' => [
            'legacy-server' => ['type' => 'stdio', 'command' => 'legacy'],
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    $server = McpServer::factory()->command()->create([
        'name' => 'filesystem',
        'enabled' => true,
    ]);

    $exporter = app(\App\Services\McpServerExporter::class);
    $exporter->export($claudeJsonPath);

    $server->update(['enabled' => false]);
    $exporter->export($claudeJsonPath);

    $config = json_decode(File::get($claudeJsonPath), true);

    expect($config['mcpServers'])
        ->toHaveKey('legacy-server')
        ->not->toHaveKey('filesystem');
});
