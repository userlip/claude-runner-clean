<?php

use App\Enums\ConnectionType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Migrate GitHub connections
        if (Schema::hasTable('github_connections')) {
            $githubConnections = DB::table('github_connections')->get();

            foreach ($githubConnections as $ghConn) {
                // Skip if already migrated (check for existing connection with this legacy_id)
                $exists = DB::table('connections')
                    ->where('user_id', $ghConn->user_id)
                    ->where('type', ConnectionType::GitHub->value)
                    ->whereRaw("JSON_EXTRACT(metadata, '$.legacy_id') = ?", [$ghConn->id])
                    ->exists();

                if ($exists) {
                    continue;
                }

                $accessToken = null;
                try {
                    $accessToken = Crypt::decryptString($ghConn->access_token);
                } catch (\Throwable) {
                    $accessToken = $ghConn->access_token;
                }

                $scopes = $ghConn->scopes;
                if (is_string($scopes)) {
                    $decoded = json_decode($scopes, true);
                    $scopes = is_array($decoded) ? $decoded : [$scopes];
                }

                DB::table('connections')->insert([
                    'user_id' => $ghConn->user_id,
                    'type' => ConnectionType::GitHub->value,
                    'name' => 'GitHub',
                    'credentials' => Crypt::encryptString($accessToken),
                    'metadata' => json_encode([
                        'github_user_id' => $ghConn->github_user_id,
                        'github_username' => $ghConn->github_username,
                        'scopes' => $scopes,
                        'legacy_id' => $ghConn->id,
                    ]),
                    'is_active' => true,
                    'created_at' => $ghConn->created_at,
                    'updated_at' => $ghConn->updated_at,
                ]);
            }
        }

        // Migrate Google Analytics connections
        if (Schema::hasTable('google_analytics_connections')) {
            $gaConnections = DB::table('google_analytics_connections')->get();

            foreach ($gaConnections as $gaConn) {
                // Skip if already migrated (check for existing connection with this legacy_id)
                $exists = DB::table('connections')
                    ->where('user_id', $gaConn->user_id)
                    ->where('type', ConnectionType::GoogleAnalytics->value)
                    ->whereRaw("JSON_EXTRACT(metadata, '$.legacy_id') = ?", [$gaConn->id])
                    ->exists();

                if ($exists) {
                    continue;
                }

                $credentialsJson = null;
                try {
                    $credentialsJson = Crypt::decryptString($gaConn->credentials_json);
                } catch (\Throwable) {
                    $credentialsJson = $gaConn->credentials_json;
                }

                DB::table('connections')->insert([
                    'user_id' => $gaConn->user_id,
                    'type' => ConnectionType::GoogleAnalytics->value,
                    'name' => $gaConn->name,
                    'credentials' => Crypt::encryptString($credentialsJson),
                    'metadata' => json_encode([
                        'property_id' => $gaConn->property_id ?? null,
                        'legacy_id' => $gaConn->id,
                    ]),
                    'is_active' => true,
                    'created_at' => $gaConn->created_at,
                    'updated_at' => $gaConn->updated_at,
                ]);
            }
        }

        // Migrate Search Console connections
        if (Schema::hasTable('search_console_connections')) {
            $scConnections = DB::table('search_console_connections')->get();

            foreach ($scConnections as $scConn) {
                // Skip if already migrated (check for existing connection with this legacy_id)
                $exists = DB::table('connections')
                    ->where('user_id', $scConn->user_id)
                    ->where('type', ConnectionType::SearchConsole->value)
                    ->whereRaw("JSON_EXTRACT(metadata, '$.legacy_id') = ?", [$scConn->id])
                    ->exists();

                if ($exists) {
                    continue;
                }

                $credentialsJson = null;
                try {
                    $credentialsJson = Crypt::decryptString($scConn->credentials_json);
                } catch (\Throwable) {
                    $credentialsJson = $scConn->credentials_json;
                }

                DB::table('connections')->insert([
                    'user_id' => $scConn->user_id,
                    'type' => ConnectionType::SearchConsole->value,
                    'name' => $scConn->name,
                    'credentials' => Crypt::encryptString($credentialsJson),
                    'metadata' => json_encode([
                        'legacy_id' => $scConn->id,
                    ]),
                    'is_active' => true,
                    'created_at' => $scConn->created_at,
                    'updated_at' => $scConn->updated_at,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Remove migrated connections (identified by having legacy_id in metadata)
        DB::table('connections')
            ->whereIn('type', [
                ConnectionType::GitHub->value,
                ConnectionType::GoogleAnalytics->value,
                ConnectionType::SearchConsole->value,
            ])
            ->whereRaw("JSON_EXTRACT(metadata, '$.legacy_id') IS NOT NULL")
            ->delete();
    }
};
