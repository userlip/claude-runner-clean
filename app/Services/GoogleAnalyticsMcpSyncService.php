<?php

namespace App\Services;

use App\Models\GoogleAnalyticsConnection;
use Illuminate\Support\Facades\File;

class GoogleAnalyticsMcpSyncService
{
    /**
     * Sync all Google Analytics connections to disk credentials and .mcp.json.
     */
    public function sync(): void
    {
        $this->ensureDirectoryExists();
        $this->writeCredentialFiles();
        $this->updateMcpConfig();
        $this->cleanupOrphanedFiles();
    }

    /**
     * Sync a single connection's credential file to disk and update .mcp.json.
     */
    public function syncConnection(GoogleAnalyticsConnection $connection): void
    {
        $this->ensureDirectoryExists();
        $this->writeCredentialFile($connection);
        $this->updateMcpConfig();
    }

    /**
     * Remove a connection's credential file from disk and update .mcp.json.
     */
    public function removeConnection(GoogleAnalyticsConnection $connection): void
    {
        $filePath = $connection->getCredentialsFilePath();
        if (File::exists($filePath)) {
            File::delete($filePath);
        }
        $this->updateMcpConfig();
    }

    private function ensureDirectoryExists(): void
    {
        $dir = storage_path('app/private/google-analytics');
        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0700, true);
        }
    }

    private function writeCredentialFile(GoogleAnalyticsConnection $connection): void
    {
        $filePath = $connection->getCredentialsFilePath();
        File::put($filePath, $connection->credentials_json);
        chmod($filePath, 0600);
    }

    private function writeCredentialFiles(): void
    {
        foreach (GoogleAnalyticsConnection::all() as $connection) {
            $this->writeCredentialFile($connection);
        }
    }

    private function updateMcpConfig(): void
    {
        $mcpPath = base_path('.mcp.json');
        $config = json_decode(File::get($mcpPath), true);

        $connections = GoogleAnalyticsConnection::all();

        if ($connections->isEmpty()) {
            unset($config['mcpServers']['google-analytics']);
        } else {
            $env = [];
            foreach ($connections as $connection) {
                $key = "GA_ACCOUNT_{$connection->getEnvKeyName()}_CREDENTIALS_PATH";
                $env[$key] = $connection->getCredentialsFilePath();
            }

            $config['mcpServers']['google-analytics'] = [
                'command' => 'node',
                'args' => [
                    base_path('mcp-servers/google-analytics-mcp/dist/index.js'),
                ],
                'env' => $env,
            ];
        }

        File::put($mcpPath, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    }

    private function cleanupOrphanedFiles(): void
    {
        $dir = storage_path('app/private/google-analytics');
        if (! File::isDirectory($dir)) {
            return;
        }

        $activeIds = GoogleAnalyticsConnection::pluck('id')->toArray();
        foreach (File::files($dir) as $file) {
            $fileId = pathinfo($file, PATHINFO_FILENAME);
            if (! in_array((int) $fileId, $activeIds)) {
                File::delete($file);
            }
        }
    }
}
