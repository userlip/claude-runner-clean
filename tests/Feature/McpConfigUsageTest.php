<?php

use App\Filament\Pages\ObservabilityDashboard;
use App\Models\Connection;
use App\Models\User;
use App\Services\GoogleAnalyticsMcpSyncService;
use App\Services\SearchConsoleMcpSyncService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->exportDir = storage_path('framework/testing/mcp-config-usage');
    $this->exportPath = "{$this->exportDir}/.claude.json";

    config()->set('mcp.export_path', $this->exportPath);
    config()->set('mcp.managed_state_path', "{$this->exportDir}/managed-state.json");

    File::deleteDirectory($this->exportDir);
    File::ensureDirectoryExists($this->exportDir);
});

afterEach(function () {
    File::deleteDirectory($this->exportDir);
});

test('observability dashboard reads MCP servers from the configured export path', function () {
    File::put($this->exportPath, json_encode([
        'theme' => 'dark',
        'mcpServers' => [
            'filesystem' => [
                'type' => 'stdio',
                'command' => 'npx',
            ],
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    $page = app(ObservabilityDashboard::class);
    $servers = $page->getMcpServersStatus();

    expect($servers)->toHaveKey('filesystem')
        ->and($servers['filesystem']['command'])->toBe('npx');
});

test('google analytics sync service updates the configured export path', function () {
    File::put($this->exportPath, json_encode([
        'theme' => 'dark',
        'mcpServers' => [],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    Connection::factory()->googleAnalytics()->create([
        'user_id' => $this->user->id,
        'name' => 'Main GA',
        'is_active' => true,
    ]);

    app(GoogleAnalyticsMcpSyncService::class)->sync();

    $config = json_decode(File::get($this->exportPath), true);

    expect($config['theme'])->toBe('dark')
        ->and($config['mcpServers'])->toHaveKey('google-analytics');
});

test('search console sync service updates the configured export path', function () {
    File::put($this->exportPath, json_encode([
        'theme' => 'dark',
        'mcpServers' => [],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    Connection::factory()->searchConsole()->create([
        'user_id' => $this->user->id,
        'name' => 'Main GSC',
        'is_active' => true,
    ]);

    app(SearchConsoleMcpSyncService::class)->sync();

    $config = json_decode(File::get($this->exportPath), true);

    expect($config['theme'])->toBe('dark')
        ->and($config['mcpServers'])->toHaveKey('search-console');
});
