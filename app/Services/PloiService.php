<?php

namespace App\Services;

use App\Enums\SiteStatus;
use App\Models\Repository;
use App\Models\Site;
use App\Support\DeployScriptHelper;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class PloiService
{
    protected string $serverId;

    protected string $serverName;

    public function __construct()
    {
        $this->serverId = config('services.ploi.server_id')
            ?? throw new \RuntimeException('Ploi server ID not configured');
        $this->serverName = config('services.ploi.server_name')
            ?? throw new \RuntimeException('Ploi server name not configured');
    }

    public function syncSites(): int
    {
        $sites = $this->fetchSites();
        $synced = 0;

        foreach ($sites as $siteData) {
            $site = Site::updateOrCreate(
                ['ploi_site_id' => $siteData['id']],
                [
                    'domain' => $siteData['domain'],
                    'php_version' => $siteData['php_version'],
                    'path' => $this->guessSitePath($siteData['domain']),
                    'status' => SiteStatus::Active,
                    'synced_from_ploi' => true,
                ]
            );

            // Try to match repository if site has one
            if ($siteData['has_repository'] && ! $site->repository_id) {
                $this->tryMatchRepository($site);
            }

            $synced++;
        }

        return $synced;
    }

    /**
     * @return array<int, array{id: string, domain: string, php_version: string, project_type: string, has_repository: bool}>
     */
    protected function fetchSites(): array
    {
        $result = Process::run([
            'ploi', 'site:list',
            '--server='.$this->serverName,
            '--no-interaction',
        ]);

        if (! $result->successful()) {
            Log::error('Failed to fetch Ploi sites', ['output' => $result->errorOutput()]);

            throw new \RuntimeException('Failed to fetch sites from Ploi: '.$result->errorOutput());
        }

        return $this->parseTableOutput($result->output());
    }

    /**
     * @return array<int, array{id: string, domain: string, php_version: string, project_type: string, has_repository: bool}>
     */
    protected function parseTableOutput(string $output): array
    {
        $sites = [];
        $lines = explode("\n", $output);

        foreach ($lines as $line) {
            // Skip header, separator, and empty lines
            if (! str_contains($line, '|') || str_contains($line, '---') || str_contains($line, 'ID')) {
                continue;
            }

            $columns = array_map('trim', explode('|', $line));
            $columns = array_values(array_filter($columns));

            if (count($columns) >= 7) {
                $sites[] = [
                    'id' => $columns[0],
                    'domain' => $columns[2],
                    'project_type' => $columns[3],
                    'php_version' => $columns[5],
                    'has_repository' => $columns[6] === 'Yes',
                ];
            }
        }

        return $sites;
    }

    protected function guessSitePath(string $domain): string
    {
        return "/home/ploi/{$domain}";
    }

    protected function tryMatchRepository(Site $site): void
    {
        // Try to match by domain name pattern (e.g., my-project.marin.sh -> my-project)
        $repoName = explode('.', $site->domain)[0];
        // Escape LIKE pattern characters
        $repoName = str_replace(['%', '_'], ['\%', '\_'], $repoName);

        $repository = Repository::where('name', 'like', "%{$repoName}%")
            ->orWhere('full_name', 'like', "%{$repoName}%")
            ->first();

        if ($repository) {
            $site->update(['repository_id' => $repository->id]);
            Log::info("Auto-matched site {$site->domain} to repository {$repository->full_name}");
        }
    }

    public function resolveSiteIdForRepository(Repository $repo, string $domain): ?string
    {
        $sites = $this->fetchSites();

        foreach ($sites as $site) {
            if ($site['domain'] === $domain) {
                $repo->update([
                    'ploi_server_id' => $this->serverId,
                    'ploi_site_id' => (string) $site['id'],
                ]);

                return (string) $site['id'];
            }
        }

        return null;
    }

    public function createReadonlyDbUser(string $serverId, string $database, string $user, string $password): bool
    {
        $result = Process::run([
            'ploi', 'database:create-user',
            '--server='.$serverId,
            '--database='.$database,
            '--user='.$user,
            '--password='.$password,
            '--readonly',
            '--no-interaction',
        ]);

        if (! $result->successful()) {
            Log::error('Failed to create readonly DB user on Ploi', [
                'server_id' => $serverId,
                'database' => $database,
                'user' => $user,
                'output' => $result->errorOutput(),
            ]);
        }

        return $result->successful();
    }

    public function fetchSiteDetails(string $serverId, string $siteId): ?array
    {
        $token = $this->getApiToken();

        if (! $token) {
            Log::warning('Ploi API token missing, cannot fetch site details.');

            return null;
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->get($this->getApiUrl()."/servers/{$serverId}/sites/{$siteId}");

        if ($response->failed()) {
            Log::warning('Failed to fetch Ploi site details.', [
                'server_id' => $serverId,
                'site_id' => $siteId,
                'status' => $response->status(),
            ]);

            return null;
        }

        return $response->json('data');
    }

    public function updateSiteDeployScript(string $serverId, string $siteId, string $deployScript): bool
    {
        $token = $this->getApiToken();

        if (! $token) {
            Log::warning('Ploi API token missing, cannot update deploy script.');

            return false;
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->patch($this->getApiUrl()."/servers/{$serverId}/sites/{$siteId}", [
                'deploy_script' => $deployScript,
            ]);

        if ($response->failed()) {
            Log::warning('Failed to update Ploi deploy script.', [
                'server_id' => $serverId,
                'site_id' => $siteId,
                'status' => $response->status(),
            ]);

            return false;
        }

        return true;
    }

    public function ensureDeployScriptCleanup(string $serverId, string $siteId, string $cleanupCommand): bool
    {
        if (! $serverId || ! $siteId) {
            return false;
        }

        $site = $this->fetchSiteDetails($serverId, $siteId);
        $current = $site['deploy_script'] ?? null;

        if ($current === null) {
            return false;
        }

        $updated = DeployScriptHelper::ensureCleanup($current, $cleanupCommand);

        if ($updated === $current) {
            return false;
        }

        return $this->updateSiteDeployScript($serverId, $siteId, $updated);
    }

    protected function getApiUrl(): string
    {
        return config('services.ploi.api_url', 'https://ploi.io/api');
    }

    protected function getApiToken(): ?string
    {
        $token = config('services.ploi.api_token');

        if ($token) {
            return $token;
        }

        $configPath = '/home/ploi/.ploi/config.php';

        if (File::exists($configPath)) {
            $config = require $configPath;

            return $config['token'] ?? null;
        }

        return null;
    }

    /**
     * Get all daemons for the server.
     *
     * @return array<int, array{id: int, command: string, directory: string, user: string, processes: int, status: string}>
     */
    public function listDaemons(): array
    {
        $result = Process::run([
            'ploi', 'daemon:list',
            '--server='.$this->serverName,
            '--no-interaction',
        ]);

        if (! $result->successful()) {
            Log::error('Failed to list Ploi daemons', ['output' => $result->errorOutput()]);

            throw new \RuntimeException('Failed to list daemons: '.$result->errorOutput());
        }

        return $this->parseDaemonOutput($result->output());
    }

    /**
     * Create a new daemon.
     *
     * @return array{id: int, command: string}|null
     */
    public function createDaemon(
        string $command,
        string $directory,
        string $user = 'ploi',
        int $processes = 1
    ): ?array {
        $result = Process::run([
            'ploi', 'daemon:create',
            '--server='.$this->serverName,
            '--command='.$command,
            '--directory='.$directory,
            '--system-user='.$user,
            '--processes='.$processes,
            '--no-interaction',
        ]);

        if (! $result->successful()) {
            Log::error('Failed to create Ploi daemon', [
                'command' => $command,
                'directory' => $directory,
                'output' => $result->errorOutput(),
            ]);

            throw new \RuntimeException('Failed to create daemon: '.$result->errorOutput());
        }

        Log::info('Ploi daemon created', [
            'command' => $command,
            'directory' => $directory,
        ]);

        return [
            'command' => $command,
            'directory' => $directory,
        ];
    }

    /**
     * Delete a daemon by ID.
     */
    public function deleteDaemon(int $daemonId): bool
    {
        $result = Process::run([
            'ploi', 'daemon:delete',
            '--server='.$this->serverName,
            '--daemon-id='.$daemonId,
            '--no-interaction',
        ]);

        if (! $result->successful()) {
            Log::error('Failed to delete Ploi daemon', [
                'daemon_id' => $daemonId,
                'output' => $result->errorOutput(),
            ]);

            return false;
        }

        Log::info('Ploi daemon deleted', ['daemon_id' => $daemonId]);

        return true;
    }

    /**
     * Restart a daemon by ID.
     */
    public function restartDaemon(int $daemonId): bool
    {
        $result = Process::run([
            'ploi', 'daemon:restart',
            '--server='.$this->serverName,
            '--daemon='.$daemonId,
            '--no-interaction',
        ]);

        if (! $result->successful()) {
            Log::error('Failed to restart Ploi daemon', [
                'daemon_id' => $daemonId,
                'output' => $result->errorOutput(),
            ]);

            return false;
        }

        Log::info('Ploi daemon restarted', ['daemon_id' => $daemonId]);

        return true;
    }

    /**
     * Find a daemon by command pattern.
     *
     * @return array{id: int, command: string, directory: string, user: string, processes: int, status: string}|null
     */
    public function findDaemonByCommand(string $pattern): ?array
    {
        $daemons = $this->listDaemons();

        foreach ($daemons as $daemon) {
            if (str_contains($daemon['command'], $pattern)) {
                return $daemon;
            }
        }

        return null;
    }

    /**
     * @return array<int, array{id: int, command: string, directory: string, user: string, processes: int, status: string}>
     */
    protected function parseDaemonOutput(string $output): array
    {
        $daemons = [];
        $lines = explode("\n", $output);

        foreach ($lines as $line) {
            // Skip header, separator, and empty lines
            if (! str_contains($line, '|') || str_contains($line, '---') || str_contains($line, 'ID')) {
                continue;
            }

            $columns = array_map('trim', explode('|', $line));
            $columns = array_values(array_filter($columns, fn ($c) => $c !== ''));

            if (count($columns) >= 6) {
                $daemons[] = [
                    'id' => (int) $columns[0],
                    'command' => $columns[1],
                    'directory' => $columns[2],
                    'user' => $columns[3],
                    'processes' => (int) $columns[4],
                    'status' => $columns[5],
                ];
            }
        }

        return $daemons;
    }
}
