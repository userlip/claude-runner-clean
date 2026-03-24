<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->exportDir = storage_path('framework/testing/mcp-model');
    $this->exportPath = "{$this->exportDir}/.mcp.json";

    config()->set('mcp.export_path', $this->exportPath);
    config()->set('mcp.managed_state_path', "{$this->exportDir}/managed-state.json");

    File::deleteDirectory($this->exportDir);
    File::ensureDirectoryExists($this->exportDir);
});

afterEach(function () {
    File::deleteDirectory($this->exportDir);
});

test('mcp server table and model exist with expected schema', function () {
    expect(class_exists(\App\Models\McpServer::class))->toBeTrue();
    expect(Schema::hasTable('mcp_servers'))->toBeTrue();

    expect(Schema::hasColumns('mcp_servers', [
        'name',
        'transport',
        'command',
        'args',
        'url',
        'headers',
        'env_vars',
        'enabled',
        'last_tested_at',
        'last_test_status',
        'last_test_message',
        'created_at',
        'updated_at',
    ]))->toBeTrue();
});

test('mcp server factory can create a command transport record', function () {
    expect(class_exists(\App\Models\McpServer::class))->toBeTrue();

    $modelClass = \App\Models\McpServer::class;

    $server = forward_static_call([$modelClass, 'factory'])
        ->command()
        ->create();

    expect($server->exists)->toBeTrue()
        ->and($server->transport)->toBe('command')
        ->and($server->command)->not->toBeNull()
        ->and($server->args)->toBeArray()
        ->and($server->env_vars)->toBeArray()
        ->and($server->enabled)->toBeBool();
});

test('mcp server casts json and encrypted attributes', function () {
    expect(class_exists(\App\Models\McpServer::class))->toBeTrue();

    $modelClass = \App\Models\McpServer::class;

    $server = forward_static_call([$modelClass, 'factory'])
        ->sse()
        ->create([
            'headers' => [
                'Authorization' => 'Bearer secret-token',
                'X-Team' => 'ops',
            ],
            'env_vars' => [
                'API_KEY' => 'top-secret',
            ],
        ]);

    $server->refresh();

    expect($server->headers)->toBe([
        'Authorization' => 'Bearer secret-token',
        'X-Team' => 'ops',
    ])->and($server->env_vars)->toBe([
        'API_KEY' => 'top-secret',
    ]);

    $raw = DB::table('mcp_servers')->where('id', $server->id)->first();

    expect($raw)->not->toBeNull()
        ->and($raw->headers)->not->toContain('secret-token')
        ->and($raw->env_vars)->not->toContain('top-secret');
});
