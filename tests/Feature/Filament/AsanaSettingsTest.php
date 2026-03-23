<?php

use App\Enums\ConnectionType;
use App\Filament\Pages\AsanaSettings;
use App\Models\Connection;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->withoutVite();
});

test('can view asana settings page when not connected', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(AsanaSettings::class)
        ->assertSuccessful()
        ->assertSee('Not Connected')
        ->assertSee('Connect to Asana');
});

test('can connect with a valid asana PAT', function () {
    $user = User::factory()->create();

    Http::fake([
        'app.asana.com/api/1.0/users/me' => Http::response(['data' => ['gid' => '123', 'name' => 'Test User']], 200),
        'app.asana.com/api/1.0/workspaces' => Http::response([
            'data' => [
                ['gid' => '12345', 'name' => 'My Workspace'],
                ['gid' => '67890', 'name' => 'Another Workspace'],
            ],
        ], 200),
    ]);

    Livewire::actingAs($user)
        ->test(AsanaSettings::class)
        ->set('personalAccessToken', 'valid-test-token-123456')
        ->call('connect')
        ->assertNotified('Asana connected');

    $connection = $user->connectionOfType(ConnectionType::Asana);
    expect($connection)->not->toBeNull();
    expect($connection->credentials)->toBe('valid-test-token-123456');
    expect($connection->metadata['default_workspace_id'])->toBe('12345');
    expect($connection->is_active)->toBeTrue();
});

test('shows validation error for invalid PAT', function () {
    $user = User::factory()->create();

    Http::fake([
        'app.asana.com/api/1.0/users/me' => Http::response(['errors' => [['message' => 'Not Authorized']]], 401),
    ]);

    Livewire::actingAs($user)
        ->test(AsanaSettings::class)
        ->set('personalAccessToken', 'invalid-token-123456')
        ->call('connect')
        ->assertNotified('Invalid Personal Access Token');

    expect($user->connectionOfType(ConnectionType::Asana))->toBeNull();
});

test('shows validation error for empty PAT', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(AsanaSettings::class)
        ->set('personalAccessToken', '')
        ->call('connect')
        ->assertHasErrors('personalAccessToken');
});

test('shows connected status with workspaces when connected', function () {
    $user = User::factory()->create();

    Connection::factory()->asana()->create([
        'user_id' => $user->id,
        'credentials' => 'stored-token-123',
        'metadata' => ['default_workspace_id' => '12345'],
    ]);

    Http::fake([
        'app.asana.com/api/1.0/workspaces' => Http::response([
            'data' => [
                ['gid' => '12345', 'name' => 'My Workspace'],
            ],
        ], 200),
    ]);

    Livewire::actingAs($user)
        ->test(AsanaSettings::class)
        ->assertSuccessful()
        ->assertSee('Connected')
        ->assertSee('My Workspace');
});

test('can save default workspace', function () {
    $user = User::factory()->create();

    $connection = Connection::factory()->asana()->create([
        'user_id' => $user->id,
        'credentials' => 'stored-token-123',
        'metadata' => ['default_workspace_id' => '12345'],
    ]);

    Http::fake([
        'app.asana.com/api/1.0/workspaces' => Http::response([
            'data' => [
                ['gid' => '12345', 'name' => 'Workspace A'],
                ['gid' => '67890', 'name' => 'Workspace B'],
            ],
        ], 200),
    ]);

    Livewire::actingAs($user)
        ->test(AsanaSettings::class)
        ->set('defaultWorkspaceId', '67890')
        ->call('saveWorkspace')
        ->assertNotified('Default workspace updated');

    expect($connection->fresh()->metadata['default_workspace_id'])->toBe('67890');
});

test('can disconnect asana', function () {
    $user = User::factory()->create();

    Connection::factory()->asana()->create([
        'user_id' => $user->id,
        'credentials' => 'stored-token-123',
    ]);

    Http::fake([
        'app.asana.com/api/1.0/workspaces' => Http::response(['data' => []], 200),
    ]);

    Livewire::actingAs($user)
        ->test(AsanaSettings::class)
        ->call('disconnect')
        ->assertNotified('Asana disconnected');

    expect($user->connectionOfType(ConnectionType::Asana))->toBeNull();
});

test('connection model encrypts credentials', function () {
    $user = User::factory()->create();

    $connection = Connection::factory()->create([
        'user_id' => $user->id,
        'type' => ConnectionType::Asana,
        'credentials' => 'my-secret-token',
    ]);

    $raw = \Illuminate\Support\Facades\DB::table('connections')
        ->where('id', $connection->id)
        ->value('credentials');

    expect($raw)->not->toBe('my-secret-token');
    expect($connection->fresh()->credentials)->toBe('my-secret-token');
});

test('connection type enum has all expected cases', function () {
    $cases = ConnectionType::cases();

    expect($cases)->toHaveCount(4);
    expect(ConnectionType::GitHub->value)->toBe('github');
    expect(ConnectionType::GoogleAnalytics->value)->toBe('google_analytics');
    expect(ConnectionType::SearchConsole->value)->toBe('search_console');
    expect(ConnectionType::Asana->value)->toBe('asana');
});

test('user can have multiple connections of different types', function () {
    $user = User::factory()->create();

    Connection::factory()->asana()->create(['user_id' => $user->id]);
    Connection::factory()->github()->create(['user_id' => $user->id]);

    expect($user->connections()->count())->toBe(2);
    expect($user->connectionOfType(ConnectionType::Asana))->not->toBeNull();
    expect($user->connectionOfType(ConnectionType::GitHub))->not->toBeNull();
});
