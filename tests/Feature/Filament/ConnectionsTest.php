<?php

use App\Enums\ConnectionType;
use App\Filament\Pages\Connections;
use App\Models\Connection;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

test('can view connections page', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(Connections::class)
        ->assertSuccessful()
        ->assertSee('All Connections');
});

test('displays all connection types as cards', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(Connections::class)
        ->assertSuccessful()
        ->assertSee('GitHub')
        ->assertSee('Google Analytics')
        ->assertSee('Search Console')
        ->assertSee('Asana');
});

test('shows not connected status when no connections exist', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(Connections::class)
        ->assertSuccessful()
        ->assertSee('Not Connected');
});

test('shows connect buttons for all integrations when not connected', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(Connections::class)
        ->assertSuccessful()
        ->assertSee('Connect');
});

test('shows connected status with details for github', function () {
    $user = User::factory()->create();
    Connection::factory()->github()->create([
        'user_id' => $user->id,
        'metadata' => [
            'github_username' => 'testuser123',
        ],
    ]);

    $this->actingAs($user);

    Livewire::test(Connections::class)
        ->assertSuccessful()
        ->assertSee('testuser123')
        ->assertSee('Connected')
        ->assertSee('Manage');
});

test('shows connected status with details for google analytics', function () {
    $user = User::factory()->create();
    Connection::factory()->create([
        'user_id' => $user->id,
        'type' => ConnectionType::GoogleAnalytics,
        'name' => 'GA Property - My Site',
        'metadata' => [
            'property_id' => '123456789',
        ],
    ]);

    $this->actingAs($user);

    Livewire::test(Connections::class)
        ->assertSuccessful()
        ->assertSee('GA Property - My Site')
        ->assertSee('Connected')
        ->assertSee('Property: 123456789');
});

test('shows connected status with details for search console', function () {
    $user = User::factory()->create();
    Connection::factory()->create([
        'user_id' => $user->id,
        'type' => ConnectionType::SearchConsole,
        'name' => 'SC Site - example.com',
    ]);

    $this->actingAs($user);

    Livewire::test(Connections::class)
        ->assertSuccessful()
        ->assertSee('SC Site - example.com')
        ->assertSee('Connected');
});

test('shows connected status with details for asana', function () {
    $user = User::factory()->create();
    Connection::factory()->asana()->create([
        'user_id' => $user->id,
        'name' => 'Asana Workspace',
    ]);

    $this->actingAs($user);

    Livewire::test(Connections::class)
        ->assertSuccessful()
        ->assertSee('Asana Workspace')
        ->assertSee('Connected');
});

test('counts connected integrations correctly', function () {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)->test(Connections::class);

    expect($component->instance()->getConnectedCount())->toBe(0);
    expect($component->instance()->getTotalCount())->toBe(4);

    Connection::factory()->github()->create(['user_id' => $user->id]);
    Connection::factory()->asana()->create(['user_id' => $user->id]);

    expect($component->instance()->getConnectedCount())->toBe(2);
});

test('getconnections returns all types with correct structure', function () {
    $user = User::factory()->create();

    Connection::factory()->github()->create([
        'user_id' => $user->id,
        'metadata' => ['github_username' => 'testuser'],
    ]);

    $component = Livewire::actingAs($user)->test(Connections::class);
    $connections = $component->instance()->getConnections();

    expect($connections)->toHaveCount(4);

    $githubConnection = $connections->first(fn ($c) => $c['type'] === ConnectionType::GitHub);
    expect($githubConnection['is_connected'])->toBeTrue();
    expect($githubConnection['details']['title'])->toBe('testuser');

    $asanaConnection = $connections->first(fn ($c) => $c['type'] === ConnectionType::Asana);
    expect($asanaConnection['is_connected'])->toBeFalse();
    expect($asanaConnection['details']['status_text'])->toBe('Not Connected');
});

test('settings routes are correct for all connection types', function () {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)->test(Connections::class);

    expect($component->instance()->getSettingsRoute(ConnectionType::GitHub))
        ->toBe(route('filament.admin.pages.git-hub-settings'));
    expect($component->instance()->getSettingsRoute(ConnectionType::GoogleAnalytics))
        ->toBe(route('filament.admin.pages.google-analytics-settings'));
    expect($component->instance()->getSettingsRoute(ConnectionType::SearchConsole))
        ->toBe(route('filament.admin.pages.search-console-settings'));
    expect($component->instance()->getSettingsRoute(ConnectionType::Asana))
        ->toBe(route('filament.admin.pages.asana-settings'));
});
