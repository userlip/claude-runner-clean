<?php

use App\Enums\ConnectionType;
use App\Models\Connection;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    // Store original tables state
    $this->legacyTablesExist = [
        'github' => Schema::hasTable('github_connections'),
        'google_analytics' => Schema::hasTable('google_analytics_connections'),
        'search_console' => Schema::hasTable('search_console_connections'),
    ];

    // Clean up any existing legacy tables for clean test
    Schema::dropIfExists('github_connections');
    Schema::dropIfExists('google_analytics_connections');
    Schema::dropIfExists('search_console_connections');
});

afterEach(function () {
    // Clean up legacy tables
    Schema::dropIfExists('github_connections');
    Schema::dropIfExists('google_analytics_connections');
    Schema::dropIfExists('search_console_connections');
});

function createLegacyTables(): void
{
    Schema::create('github_connections', function ($table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->text('access_token');
        $table->string('github_user_id')->nullable();
        $table->string('github_username')->nullable();
        $table->json('scopes')->nullable();
        $table->timestamps();
    });

    Schema::create('google_analytics_connections', function ($table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->text('credentials_json');
        $table->string('name');
        $table->string('property_id')->nullable();
        $table->timestamps();
    });

    Schema::create('search_console_connections', function ($table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->text('credentials_json');
        $table->string('name');
        $table->timestamps();
    });
}

function insertLegacyGitHubData(User $user, array $data): int
{
    return DB::table('github_connections')->insertGetId([
        'user_id' => $user->id,
        'access_token' => $data['access_token'] ?? Crypt::encryptString('gh_test_token'),
        'github_user_id' => $data['github_user_id'] ?? '123456',
        'github_username' => $data['github_username'] ?? 'testuser',
        'scopes' => json_encode($data['scopes'] ?? ['repo', 'read:user']),
        'created_at' => now()->subDays(5),
        'updated_at' => now()->subDay(),
    ]);
}

function insertLegacyGAData(User $user, array $data): int
{
    return DB::table('google_analytics_connections')->insertGetId([
        'user_id' => $user->id,
        'credentials_json' => $data['credentials_json'] ?? Crypt::encryptString(json_encode([
            'type' => 'service_account',
            'client_email' => 'ga@example.com',
        ])),
        'name' => $data['name'] ?? 'GA Property - Test Site',
        'property_id' => $data['property_id'] ?? '123456789',
        'created_at' => now()->subDays(3),
        'updated_at' => now()->subHours(2),
    ]);
}

function insertLegacySCData(User $user, array $data): int
{
    return DB::table('search_console_connections')->insertGetId([
        'user_id' => $user->id,
        'credentials_json' => $data['credentials_json'] ?? Crypt::encryptString(json_encode([
            'type' => 'service_account',
            'client_email' => 'sc@example.com',
        ])),
        'name' => $data['name'] ?? 'SC Site - example.com',
        'created_at' => now()->subDays(2),
        'updated_at' => now()->subHour(),
    ]);
}

function runMigration(): void
{
    $migration = include database_path('migrations/2026_03_23_133658_migrate_integrations_to_connections_table.php');
    $migration->up();
}

function rollbackMigration(): void
{
    $migration = include database_path('migrations/2026_03_23_133658_migrate_integrations_to_connections_table.php');
    $migration->down();
}

test('migration migrates github connections correctly', function () {
    createLegacyTables();
    $user = User::factory()->create();
    $legacyId = insertLegacyGitHubData($user, [
        'github_username' => 'migrateduser',
        'github_user_id' => '987654',
        'scopes' => ['repo', 'read:org'],
    ]);

    runMigration();

    $connection = Connection::where('user_id', $user->id)
        ->where('type', ConnectionType::GitHub)
        ->first();

    expect($connection)->not->toBeNull();
    expect($connection->name)->toBe('GitHub');
    expect($connection->credentials)->toBe('gh_test_token');
    expect($connection->metadata['github_username'])->toBe('migrateduser');
    expect($connection->metadata['github_user_id'])->toBe('987654');
    expect($connection->metadata['scopes'])->toBe(['repo', 'read:org']);
    expect($connection->metadata['legacy_id'])->toBe($legacyId);
    expect($connection->is_active)->toBeTrue();
});

test('migration migrates google analytics connections correctly', function () {
    createLegacyTables();
    $user = User::factory()->create();
    $legacyId = insertLegacyGAData($user, [
        'name' => 'Test GA Property',
        'property_id' => 'properties/987654321',
    ]);

    runMigration();

    $connection = Connection::where('user_id', $user->id)
        ->where('type', ConnectionType::GoogleAnalytics)
        ->first();

    expect($connection)->not->toBeNull();
    expect($connection->name)->toBe('Test GA Property');
    expect($connection->metadata['property_id'])->toBe('properties/987654321');
    expect($connection->metadata['legacy_id'])->toBe($legacyId);
    expect($connection->is_active)->toBeTrue();
});

test('migration migrates search console connections correctly', function () {
    createLegacyTables();
    $user = User::factory()->create();
    $legacyId = insertLegacySCData($user, [
        'name' => 'Test SC Site',
    ]);

    runMigration();

    $connection = Connection::where('user_id', $user->id)
        ->where('type', ConnectionType::SearchConsole)
        ->first();

    expect($connection)->not->toBeNull();
    expect($connection->name)->toBe('Test SC Site');
    expect($connection->metadata['legacy_id'])->toBe($legacyId);
    expect($connection->is_active)->toBeTrue();
});

test('migration migrates multiple connection types for same user', function () {
    createLegacyTables();
    $user = User::factory()->create();

    insertLegacyGitHubData($user, ['github_username' => 'multitest']);
    insertLegacyGAData($user, ['name' => 'Multi GA']);
    insertLegacySCData($user, ['name' => 'Multi SC']);

    runMigration();

    $connections = Connection::where('user_id', $user->id)->get();

    expect($connections)->toHaveCount(3);
    expect($connections->pluck('type.value'))->toContain(
        ConnectionType::GitHub->value,
        ConnectionType::GoogleAnalytics->value,
        ConnectionType::SearchConsole->value
    );
});

test('migration preserves timestamps from legacy tables', function () {
    createLegacyTables();
    $user = User::factory()->create();
    $createdAt = now()->subDays(10);
    $updatedAt = now()->subDays(5);

    DB::table('github_connections')->insert([
        'user_id' => $user->id,
        'access_token' => Crypt::encryptString('token'),
        'github_username' => 'timetest',
        'created_at' => $createdAt,
        'updated_at' => $updatedAt,
    ]);

    runMigration();

    $connection = Connection::where('user_id', $user->id)->first();

    expect($connection->created_at->format('Y-m-d H:i:s'))->toBe($createdAt->format('Y-m-d H:i:s'));
    expect($connection->updated_at->format('Y-m-d H:i:s'))->toBe($updatedAt->format('Y-m-d H:i:s'));
});

test('migration handles non-encrypted credentials gracefully', function () {
    createLegacyTables();
    $user = User::factory()->create();

    DB::table('github_connections')->insert([
        'user_id' => $user->id,
        'access_token' => 'plain_text_token',
        'github_username' => 'plaintextuser',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    runMigration();

    $connection = Connection::where('user_id', $user->id)->first();

    expect($connection)->not->toBeNull();
    expect($connection->credentials)->toBe('plain_text_token');
});

test('migration handles string scopes in legacy data', function () {
    createLegacyTables();
    $user = User::factory()->create();

    DB::table('github_connections')->insert([
        'user_id' => $user->id,
        'access_token' => Crypt::encryptString('token'),
        'github_username' => 'scopeuser',
        'scopes' => 'repo', // String instead of JSON array
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    runMigration();

    $connection = Connection::where('user_id', $user->id)->first();

    expect($connection->metadata['scopes'])->toBe(['repo']);
});

test('rollback removes only migrated connections', function () {
    createLegacyTables();
    $user = User::factory()->create();

    // Create a pre-existing Asana connection (not from migration)
    $asanaConnection = Connection::factory()->asana()->create([
        'user_id' => $user->id,
        'name' => 'Pre-existing Asana',
        'metadata' => ['default_workspace_id' => '12345'], // No legacy_id
    ]);

    // Create a legacy GitHub connection and migrate it
    insertLegacyGitHubData($user, ['github_username' => 'rollbacktest']);
    runMigration();

    expect(Connection::where('user_id', $user->id)->count())->toBe(2);

    rollbackMigration();

    // Only the migrated connection should be removed
    $remainingConnections = Connection::where('user_id', $user->id)->get();
    expect($remainingConnections)->toHaveCount(1);
    expect($remainingConnections->first()->id)->toBe($asanaConnection->id);
    expect($remainingConnections->first()->name)->toBe('Pre-existing Asana');
});

test('rollback handles mixed migrated and non-migrated connections', function () {
    createLegacyTables();
    $user = User::factory()->create();

    // Create pre-existing connections without legacy_id
    Connection::factory()->asana()->create([
        'user_id' => $user->id,
        'name' => 'Direct Asana',
        'metadata' => ['default_workspace_id' => '111'],
    ]);

    // Migrate legacy connections
    insertLegacyGitHubData($user, ['github_username' => 'gh1']);
    insertLegacyGAData($user, ['name' => 'GA1']);
    insertLegacySCData($user, ['name' => 'SC1']);
    runMigration();

    expect(Connection::where('user_id', $user->id)->count())->toBe(4);

    rollbackMigration();

    // Only the 3 migrated connections should be removed
    $remaining = Connection::where('user_id', $user->id)->get();
    expect($remaining)->toHaveCount(1);
    expect($remaining->first()->type)->toBe(ConnectionType::Asana);
});

test('migration skips when legacy tables do not exist', function () {
    // Don't create legacy tables - migration should complete without error
    expect(fn () => runMigration())->not->toThrow(\Throwable::class);

    expect(Connection::count())->toBe(0);
});

test('migration uses insertOrIgnore for duplicate prevention', function () {
    createLegacyTables();
    $user = User::factory()->create();

    // Insert same legacy record twice by running migration twice
    insertLegacyGitHubData($user, ['github_username' => 'duptest']);

    runMigration();
    runMigration(); // Second run should not create duplicates

    $connections = Connection::where('user_id', $user->id)
        ->where('type', ConnectionType::GitHub)
        ->get();

    expect($connections)->toHaveCount(1);
});
