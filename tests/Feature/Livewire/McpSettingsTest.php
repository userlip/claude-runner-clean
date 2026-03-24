<?php

use App\Livewire\Settings\Mcp;
use App\Models\McpServer;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->user = User::factory()->create();

    $this->exportDir = storage_path('framework/testing/workbench-mcp-settings');
    $this->exportPath = "{$this->exportDir}/.claude.json";

    config()->set('mcp.export_path', $this->exportPath);
    config()->set('mcp.managed_state_path', "{$this->exportDir}/managed-state.json");

    File::deleteDirectory($this->exportDir);
    File::ensureDirectoryExists($this->exportDir);
});

afterEach(function () {
    File::deleteDirectory($this->exportDir);
});

it('redirects unauthenticated users to login for workbench MCP settings', function () {
    $this->get('/workbench/settings/mcp')->assertRedirect('/admin/login');
});

it('returns 403 for non-admin users on workbench MCP settings', function () {
    $this->actingAs($this->user)->get('/workbench/settings/mcp')->assertForbidden();
});

it('renders the workbench MCP settings page for admins', function () {
    $server = McpServer::factory()->command()->create([
        'name' => 'filesystem',
    ]);

    $this->actingAs($this->admin)->get('/workbench/settings/mcp')->assertOk();

    Livewire::actingAs($this->admin)
        ->test(Mcp::class)
        ->assertSuccessful()
        ->assertSee('MCP Servers')
        ->assertSee($server->name);
});

it('creates a command transport MCP server from the workbench page', function () {
    Livewire::actingAs($this->admin)
        ->test(Mcp::class)
        ->set('newName', 'filesystem')
        ->set('newTransport', 'command')
        ->set('newCommand', 'npx')
        ->set('newArgsText', "-y\n@modelcontextprotocol/server-filesystem\n/srv/app")
        ->set('newEnvVarsText', 'ROOT_PATH=/srv/app')
        ->call('addServer');

    $server = McpServer::query()->where('name', 'filesystem')->first();

    expect($server)->not->toBeNull()
        ->and($server->transport)->toBe('command')
        ->and($server->args)->toBe(['-y', '@modelcontextprotocol/server-filesystem', '/srv/app'])
        ->and(File::exists($this->exportPath))->toBeTrue();
});

it('persists a test result when the saved workbench MCP configuration is tested', function () {
    Process::fake([
        '*' => Process::result(output: 'MCP ready', exitCode: 0),
    ]);

    $server = McpServer::factory()->command()->create([
        'headers' => [],
        'last_test_status' => null,
        'last_test_message' => null,
    ]);

    Livewire::actingAs($this->admin)
        ->test(Mcp::class)
        ->call('testServer', $server->id);

    $server->refresh();

    expect($server->last_test_status)->toBe('success')
        ->and($server->last_test_message)->toContain('MCP ready');
});

it('tests draft workbench MCP changes without persisting the status', function () {
    Http::fake([
        'https://old.example.com/mcp' => Http::response('old config', 500),
        'https://new.example.com/mcp' => Http::response('new config', 200),
    ]);

    $server = McpServer::factory()->sse()->create([
        'url' => 'https://old.example.com/mcp',
        'last_test_status' => null,
        'last_test_message' => null,
    ]);

    Livewire::actingAs($this->admin)
        ->test(Mcp::class)
        ->set("serversForm.{$server->id}.url", 'https://new.example.com/mcp')
        ->call('testServer', $server->id);

    $server->refresh();

    expect($server->last_test_status)->toBeNull()
        ->and($server->last_test_message)->toBeNull();
});

it('renders a setup notice instead of crashing when the MCP table is missing', function () {
    Schema::drop('mcp_servers');

    $this->actingAs($this->admin)
        ->get('/workbench/settings/mcp')
        ->assertOk()
        ->assertSee('MCP setup is not complete');

    Livewire::actingAs($this->admin)
        ->test(Mcp::class)
        ->assertSee('MCP setup is not complete');
});
