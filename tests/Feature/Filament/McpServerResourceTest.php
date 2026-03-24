<?php

use App\Filament\Resources\McpServers\McpServerResource;
use App\Filament\Resources\McpServers\Pages\CreateMcpServer;
use App\Filament\Resources\McpServers\Pages\EditMcpServer;
use App\Filament\Resources\McpServers\Pages\ListMcpServers;
use App\Models\McpServer;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Spatie\Permission\Models\Role;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->user = User::factory()->create();
    Role::firstOrCreate(['name' => 'admin']);
    $this->user->assignRole('admin');
    $this->actingAs($this->user);

    $this->exportDir = storage_path('framework/testing/mcp-resource');
    $this->exportPath = "{$this->exportDir}/.mcp.json";

    config()->set('mcp.export_path', $this->exportPath);
    config()->set('mcp.managed_state_path', "{$this->exportDir}/managed-state.json");

    File::deleteDirectory($this->exportDir);
    File::ensureDirectoryExists($this->exportDir);
});

afterEach(function () {
    File::deleteDirectory($this->exportDir);
});

test('mcp server resource appears in settings navigation group', function () {
    expect(class_exists(McpServerResource::class))->toBeTrue();
    expect(McpServerResource::getNavigationGroup())->toBe('Settings');
});

test('mcp server resource is restricted to admins', function () {
    expect(class_exists(McpServerResource::class))->toBeTrue();

    $regularUser = User::factory()->create();
    $this->actingAs($regularUser);

    expect(McpServerResource::canAccess())->toBeFalse();

    $this->actingAs($this->user);

    expect(McpServerResource::canAccess())->toBeTrue();
});

test('can view mcp servers list', function () {
    expect(class_exists(ListMcpServers::class))->toBeTrue();

    $server = McpServer::factory()->create([
        'name' => 'filesystem',
    ]);

    livewire(ListMcpServers::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$server]);
});

test('can create a command transport mcp server and auto export it', function () {
    expect(class_exists(CreateMcpServer::class))->toBeTrue();

    livewire(CreateMcpServer::class)
        ->fillForm([
            'name' => 'filesystem',
            'transport' => 'command',
            'command' => 'npx',
            'args' => ['-y', '@modelcontextprotocol/server-filesystem', '/srv/app'],
            'env_vars' => ['ROOT_PATH' => '/srv/app'],
            'enabled' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $server = McpServer::where('name', 'filesystem')->first();

    expect($server)->not->toBeNull()
        ->and($server->transport)->toBe('command')
        ->and(File::exists($this->exportPath))->toBeTrue();

    $config = json_decode(File::get($this->exportPath), true);

    expect($config['mcpServers'])->toHaveKey('filesystem');
});

test('can create an sse transport mcp server', function () {
    expect(class_exists(CreateMcpServer::class))->toBeTrue();

    livewire(CreateMcpServer::class)
        ->fillForm([
            'name' => 'remote-docs',
            'transport' => 'sse',
            'url' => 'https://docs.example.com/mcp',
            'headers' => ['Authorization' => 'Bearer abc123'],
            'enabled' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $server = McpServer::where('name', 'remote-docs')->first();

    expect($server)->not->toBeNull()
        ->and($server->transport)->toBe('sse')
        ->and($server->url)->toBe('https://docs.example.com/mcp');
});

test('transport specific validation requires command fields for command transport', function () {
    expect(class_exists(CreateMcpServer::class))->toBeTrue();

    livewire(CreateMcpServer::class)
        ->fillForm([
            'name' => 'filesystem',
            'transport' => 'command',
            'command' => null,
        ])
        ->call('create')
        ->assertHasFormErrors(['command']);
});

test('transport specific validation requires url for sse transport', function () {
    expect(class_exists(CreateMcpServer::class))->toBeTrue();

    livewire(CreateMcpServer::class)
        ->fillForm([
            'name' => 'remote-docs',
            'transport' => 'sse',
            'url' => null,
        ])
        ->call('create')
        ->assertHasFormErrors(['url']);
});

test('create page test connection action validates current form data without saving', function () {
    expect(class_exists(CreateMcpServer::class))->toBeTrue();

    Process::fake([
        '*' => Process::result(output: 'MCP ready', exitCode: 0),
    ]);

    livewire(CreateMcpServer::class)
        ->fillForm([
            'name' => 'filesystem',
            'transport' => 'command',
            'command' => 'npx',
            'args' => ['-y', '@modelcontextprotocol/server-filesystem', '/srv/app'],
            'enabled' => true,
        ])
        ->callAction('test_connection')
        ->assertNotified('Connection test succeeded');

    expect(McpServer::where('name', 'filesystem')->exists())->toBeFalse();
});

test('can edit an mcp server and switch transports', function () {
    expect(class_exists(EditMcpServer::class))->toBeTrue();

    $server = McpServer::factory()->command()->create([
        'name' => 'filesystem',
    ]);

    livewire(EditMcpServer::class, ['record' => $server->getRouteKey()])
        ->fillForm([
            'name' => 'filesystem',
            'transport' => 'sse',
            'command' => null,
            'args' => [],
            'url' => 'https://docs.example.com/mcp',
            'headers' => ['Authorization' => 'Bearer xyz'],
            'env_vars' => [],
            'enabled' => true,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $server->refresh();

    expect($server->transport)->toBe('sse')
        ->and($server->command)->toBeNull()
        ->and($server->url)->toBe('https://docs.example.com/mcp');
});

test('edit page test connection action updates saved status', function () {
    expect(class_exists(EditMcpServer::class))->toBeTrue();

    Process::fake([
        '*' => Process::result(output: 'MCP ready', exitCode: 0),
    ]);

    $server = McpServer::factory()->command()->create([
        'last_test_status' => null,
        'last_test_message' => null,
    ]);

    livewire(EditMcpServer::class, ['record' => $server->getRouteKey()])
        ->callAction('test_connection')
        ->assertNotified('Connection test succeeded');

    $server->refresh();

    expect($server->last_test_status)->toBe('success')
        ->and($server->last_test_message)->toContain('MCP ready');
});

test('edit page test connection action uses current unsaved form state without persisting draft test status', function () {
    expect(class_exists(EditMcpServer::class))->toBeTrue();

    Http::fake([
        'https://old.example.com/mcp' => Http::response('old config', 500),
        'https://new.example.com/mcp' => Http::response('new config', 200),
    ]);

    $server = McpServer::factory()->sse()->create([
        'url' => 'https://old.example.com/mcp',
    ]);

    livewire(EditMcpServer::class, ['record' => $server->getRouteKey()])
        ->fillForm([
            'name' => $server->name,
            'transport' => 'sse',
            'url' => 'https://new.example.com/mcp',
            'headers' => [],
            'enabled' => true,
        ])
        ->callAction('test_connection')
        ->assertNotified('Connection test succeeded');

    $server->refresh();

    expect($server->last_test_status)->toBeNull()
        ->and($server->last_test_message)->toBeNull();
});

test('table test connection action updates status', function () {
    expect(class_exists(ListMcpServers::class))->toBeTrue();

    Http::fake([
        'https://docs.example.com/mcp' => Http::response('ok', 200),
    ]);

    $server = McpServer::factory()->sse()->create([
        'url' => 'https://docs.example.com/mcp',
    ]);

    livewire(ListMcpServers::class)
        ->callTableAction('test_connection', $server)
        ->assertNotified('Connection test succeeded');

    $server->refresh();

    expect($server->last_test_status)->toBe('success');
});

test('bulk test action updates selected records', function () {
    expect(class_exists(ListMcpServers::class))->toBeTrue();

    Process::fake([
        '*' => Process::result(output: 'MCP ready', exitCode: 0),
    ]);

    $servers = McpServer::factory()->command()->count(2)->create();

    livewire(ListMcpServers::class)
        ->selectTableRecords($servers)
        ->callAction(TestAction::make('test_all')->table()->bulk())
        ->assertHasNoActionErrors()
        ->assertNotified('Connection tests completed');

    expect($servers->fresh()->every(fn (McpServer $server) => $server->last_test_status === 'success'))->toBeTrue();
});

test('disabling a server removes it from the exported config and delete re-exports', function () {
    expect(class_exists(EditMcpServer::class))->toBeTrue();

    $server = McpServer::factory()->command()->create([
        'name' => 'filesystem',
        'enabled' => true,
    ]);

    $initialConfig = json_decode(File::get($this->exportPath), true);
    expect($initialConfig['mcpServers'])->toHaveKey('filesystem');

    livewire(EditMcpServer::class, ['record' => $server->getRouteKey()])
        ->fillForm([
            'name' => 'filesystem',
            'transport' => 'command',
            'command' => 'npx',
            'args' => ['-y', '@modelcontextprotocol/server-filesystem', '/tmp'],
            'env_vars' => ['ROOT_PATH' => '/tmp'],
            'enabled' => false,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $disabledConfig = json_decode(File::get($this->exportPath), true);
    expect($disabledConfig['mcpServers'])->not->toHaveKey('filesystem');

    $server->delete();

    $deletedConfig = json_decode(File::get($this->exportPath), true);
    expect($deletedConfig['mcpServers'])->not->toHaveKey('filesystem');
});
