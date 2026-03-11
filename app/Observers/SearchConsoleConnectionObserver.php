<?php

namespace App\Observers;

use App\Models\SearchConsoleConnection;
use App\Services\SearchConsoleMcpSyncService;
use Illuminate\Support\Facades\Log;

class SearchConsoleConnectionObserver
{
    public function __construct(private SearchConsoleMcpSyncService $syncService) {}

    public function created(SearchConsoleConnection $connection): void
    {
        try {
            $this->syncService->syncConnection($connection);
        } catch (\Throwable $e) {
            Log::error('Failed to sync Search Console connection on create', [
                'connection_id' => $connection->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function updated(SearchConsoleConnection $connection): void
    {
        try {
            $this->syncService->syncConnection($connection);
        } catch (\Throwable $e) {
            Log::error('Failed to sync Search Console connection on update', [
                'connection_id' => $connection->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function deleted(SearchConsoleConnection $connection): void
    {
        try {
            $this->syncService->removeConnection($connection);
        } catch (\Throwable $e) {
            Log::error('Failed to remove Search Console connection on delete', [
                'connection_id' => $connection->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
