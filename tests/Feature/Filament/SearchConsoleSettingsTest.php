<?php

use App\Filament\Pages\SearchConsoleSettings;
use App\Models\Connection;
use App\Models\User;
use App\Services\SearchConsoleMcpSyncService;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->withoutVite();

    // Mock sync service so tests don't write to real credential files or .mcp.json
    $this->mock(SearchConsoleMcpSyncService::class, function ($mock) {
        $mock->shouldReceive('syncConnection', 'removeConnection', 'sync')->zeroOrMoreTimes();
    });
});

test('can view search console settings page', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(SearchConsoleSettings::class)
        ->assertSuccessful()
        ->assertSee('No Connections');
});

test('shows existing connections', function () {
    $user = User::factory()->create();
    Connection::factory()->searchConsole()->create([
        'user_id' => $user->id,
        'name' => 'Test Client',
    ]);

    Livewire::actingAs($user)
        ->test(SearchConsoleSettings::class)
        ->assertSuccessful()
        ->assertSee('Test Client')
        ->assertDontSee('No Connections');
});

test('can add a new connection with valid service account json', function () {
    $user = User::factory()->create();

    $credentialsJson = json_encode([
        'type' => 'service_account',
        'project_id' => 'test-project',
        'private_key_id' => 'abc123',
        'private_key' => "-----BEGIN RSA PRIVATE KEY-----\nMIItest\n-----END RSA PRIVATE KEY-----\n",
        'client_email' => 'test@test-project.iam.gserviceaccount.com',
        'client_id' => '12345',
        'auth_uri' => 'https://accounts.google.com/o/oauth2/auth',
        'token_uri' => 'https://oauth2.googleapis.com/token',
    ]);

    $file = UploadedFile::fake()->createWithContent('credentials.json', $credentialsJson);

    Livewire::actingAs($user)
        ->test(SearchConsoleSettings::class)
        ->set('newConnectionName', 'My Search Console')
        ->set('credentialsFile', $file)
        ->call('addConnection')
        ->assertNotified();

    expect($user->searchConsoleConnections()->count())->toBe(1);

    $connection = $user->searchConsoleConnections()->first();
    expect($connection->name)->toBe('My Search Console');
    expect($connection->getClientEmail())->toBe('test@test-project.iam.gserviceaccount.com');
});

test('rejects non-service-account json file', function () {
    $user = User::factory()->create();

    $invalidJson = json_encode(['type' => 'authorized_user', 'client_id' => '123']);
    $file = UploadedFile::fake()->createWithContent('credentials.json', $invalidJson);

    Livewire::actingAs($user)
        ->test(SearchConsoleSettings::class)
        ->set('newConnectionName', 'Invalid Account')
        ->set('credentialsFile', $file)
        ->call('addConnection')
        ->assertNotified();

    expect($user->searchConsoleConnections()->count())->toBe(0);
});

test('can delete a connection', function () {
    $user = User::factory()->create();
    $connection = Connection::factory()->searchConsole()->create([
        'user_id' => $user->id,
        'name' => 'To Delete',
    ]);

    Livewire::actingAs($user)
        ->test(SearchConsoleSettings::class)
        ->call('deleteConnection', $connection->id)
        ->assertNotified();

    expect($user->searchConsoleConnections()->count())->toBe(0);
});

test('supports multiple connections per user', function () {
    $user = User::factory()->create();
    Connection::factory()->searchConsole()->count(3)->create([
        'user_id' => $user->id,
    ]);

    expect($user->searchConsoleConnections()->count())->toBe(3);

    Livewire::actingAs($user)
        ->test(SearchConsoleSettings::class)
        ->assertSuccessful();
});
