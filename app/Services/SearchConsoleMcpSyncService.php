<?php

namespace App\Services;

use App\Enums\ConnectionType;
use App\Models\Connection;
use App\Models\SearchConsoleConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;

class SearchConsoleMcpSyncService
{
    public function __construct(
        private readonly McpConfigService $configService,
    ) {}

    /**
     * Sync all Search Console connections to disk credentials and the configured MCP config.
     */
    public function sync(): void
    {
        $this->ensureDirectoryExists();
        $this->writeCredentialFiles();
        $this->updateMcpConfig();
        $this->cleanupOrphanedFiles();
    }

    /**
     * Sync a single connection's credential file to disk and update the configured MCP config.
     */
    public function syncConnection(SearchConsoleConnection|Connection $connection): void
    {
        $this->ensureDirectoryExists();
        $this->writeCredentialFile($connection);
        $this->updateMcpConfig();
    }

    /**
     * Remove a connection's credential file from disk and update the configured MCP config.
     */
    public function removeConnection(SearchConsoleConnection|Connection $connection): void
    {
        $filePath = $connection->getCredentialsFilePath();
        if (File::exists($filePath)) {
            File::delete($filePath);
        }
        $this->updateMcpConfig();
    }

    private function ensureDirectoryExists(): void
    {
        $dir = storage_path('app/private/search-console');
        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0700, true);
        }
    }

    private function writeCredentialFile(SearchConsoleConnection|Connection $connection): void
    {
        $filePath = $connection->getCredentialsFilePath();
        $credentials = $connection instanceof Connection ? $connection->credentials : $connection->credentials_json;
        File::put($filePath, $credentials);
        chmod($filePath, 0600);
    }

    private function writeCredentialFiles(): void
    {
        foreach ($this->getAllConnections() as $connection) {
            $this->writeCredentialFile($connection);
        }
    }

    private function updateMcpConfig(): void
    {
        $connections = $this->getAllConnections();

        if ($connections->isEmpty()) {
            $this->configService->forgetServer('search-console');
        } else {
            $env = [];
            foreach ($connections as $connection) {
                $key = "GSC_ACCOUNT_{$connection->getEnvKeyName()}_CREDENTIALS_PATH";
                $env[$key] = $connection->getCredentialsFilePath();
            }

            $this->configService->putServer('search-console', [
                'type' => 'stdio',
                'command' => 'node',
                'args' => [
                    base_path('mcp-servers/search-console-mcp/dist/index.js'),
                ],
                'env' => $env,
            ]);
        }
    }

    private function cleanupOrphanedFiles(): void
    {
        $dir = storage_path('app/private/search-console');
        if (! File::isDirectory($dir)) {
            return;
        }

        $activeIds = $this->getAllConnections()->pluck('id')->toArray();
        foreach (File::files($dir) as $file) {
            $fileId = pathinfo($file, PATHINFO_FILENAME);
            if (! in_array((int) $fileId, $activeIds)) {
                File::delete($file);
            }
        }
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Model>
     */
    private function getAllConnections(): \Illuminate\Database\Eloquent\Collection
    {
        return Connection::where('type', ConnectionType::SearchConsole)->where('is_active', true)->get();
    }
}
