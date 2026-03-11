<?php

namespace App\Console\Commands;

use App\Services\SearchConsoleMcpSyncService;
use Illuminate\Console\Command;

class SyncSearchConsoleMcpCommand extends Command
{
    protected $signature = 'search-console:sync-mcp';

    protected $description = 'Sync Search Console connections to MCP server configuration';

    public function handle(SearchConsoleMcpSyncService $syncService): int
    {
        $this->info('Syncing Search Console MCP configuration...');

        $syncService->sync();

        $this->info('Done.');

        return self::SUCCESS;
    }
}
