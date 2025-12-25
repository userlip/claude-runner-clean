<?php

namespace App\Services;

use App\Enums\SiteStatus;
use App\Models\Repository;
use App\Models\Site;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class PloiService
{
    protected string $serverId;

    public function __construct()
    {
        $this->serverId = config('services.ploi.server_id');
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
            '--server='.$this->serverId,
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

        $repository = Repository::where('name', 'like', "%{$repoName}%")
            ->orWhere('full_name', 'like', "%{$repoName}%")
            ->first();

        if ($repository) {
            $site->update(['repository_id' => $repository->id]);
            Log::info("Auto-matched site {$site->domain} to repository {$repository->full_name}");
        }
    }
}
