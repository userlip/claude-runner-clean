<?php

namespace App\Observers;

use App\Models\GoogleAnalyticsConnection;
use App\Services\GoogleAnalyticsMcpSyncService;
use Illuminate\Support\Facades\Log;

class GoogleAnalyticsConnectionObserver
{
    public function __construct(private GoogleAnalyticsMcpSyncService $syncService) {}

    public function created(GoogleAnalyticsConnection $connection): void
    {
        try {
            $this->syncService->syncConnection($connection);
        } catch (\Throwable $e) {
            Log::error('Failed to sync GA connection on create', [
                'connection_id' => $connection->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function updated(GoogleAnalyticsConnection $connection): void
    {
        try {
            $this->syncService->syncConnection($connection);
        } catch (\Throwable $e) {
            Log::error('Failed to sync GA connection on update', [
                'connection_id' => $connection->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function deleted(GoogleAnalyticsConnection $connection): void
    {
        try {
            $this->syncService->removeConnection($connection);
        } catch (\Throwable $e) {
            Log::error('Failed to remove GA connection on delete', [
                'connection_id' => $connection->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
