<?php

namespace App\Console\Commands;

use App\Services\GoogleAnalyticsMcpSyncService;
use Illuminate\Console\Command;

class SyncGoogleAnalyticsMcpCommand extends Command
{
    protected $signature = 'google-analytics:sync-mcp';

    protected $description = 'Sync Google Analytics connections to MCP server credentials';

    public function handle(GoogleAnalyticsMcpSyncService $service): int
    {
        $service->sync();

        $this->info('Google Analytics MCP credentials synced successfully.');

        return self::SUCCESS;
    }
}
