<?php

namespace App\Observers;

use App\Enums\ConnectionType;
use App\Models\Connection;
use App\Services\GoogleAnalyticsMcpSyncService;
use App\Services\SearchConsoleMcpSyncService;
use Illuminate\Support\Facades\Log;

class ConnectionObserver
{
    public function created(Connection $connection): void
    {
        $this->syncIfApplicable($connection, 'create');
    }

    public function updated(Connection $connection): void
    {
        $this->syncIfApplicable($connection, 'update');
    }

    public function deleted(Connection $connection): void
    {
        $this->removeIfApplicable($connection);
    }

    private function syncIfApplicable(Connection $connection, string $action): void
    {
        try {
            match ($connection->type) {
                ConnectionType::GoogleAnalytics => app(GoogleAnalyticsMcpSyncService::class)->syncConnection($connection),
                ConnectionType::SearchConsole => app(SearchConsoleMcpSyncService::class)->syncConnection($connection),
                default => null,
            };
        } catch (\Throwable $e) {
            Log::error("Failed to sync connection on {$action}", [
                'connection_id' => $connection->id,
                'type' => $connection->type->value,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function removeIfApplicable(Connection $connection): void
    {
        try {
            match ($connection->type) {
                ConnectionType::GoogleAnalytics => app(GoogleAnalyticsMcpSyncService::class)->removeConnection($connection),
                ConnectionType::SearchConsole => app(SearchConsoleMcpSyncService::class)->removeConnection($connection),
                default => null,
            };
        } catch (\Throwable $e) {
            Log::error('Failed to remove connection on delete', [
                'connection_id' => $connection->id,
                'type' => $connection->type->value,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
