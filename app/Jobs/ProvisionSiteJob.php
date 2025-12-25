<?php

namespace App\Jobs;

use App\Models\Site;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class ProvisionSiteJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(public Site $site) {}

    public function handle(): void
    {
        $this->site->markAsProvisioning();

        try {
            $serverId = config('services.ploi.server_id');

            Log::info("Provisioning site: {$this->site->domain}", [
                'site_id' => $this->site->id,
                'repository' => $this->site->repository->full_name,
                'server_id' => $serverId,
            ]);

            // Step 1: Create the site via Ploi CLI
            $createResult = $this->runPloiCommand([
                'site:create',
                '--server='.$serverId,
                '--domain='.$this->site->domain,
                '--web-directory='.($this->site->web_directory ?? '/public'),
                '--project-type=laravel',
                $this->site->isolated_user ? '--system-user='.$this->getSystemUser() : '',
                '--no-interaction',
            ]);

            if (! $createResult['success']) {
                throw new \RuntimeException("Failed to create site: {$createResult['output']}");
            }

            // Extract site ID from the output (Ploi CLI outputs the created site ID)
            $ploiSiteId = $this->extractSiteId($createResult['output']);

            // Step 2: Install repository
            $repoResult = $this->runPloiCommand([
                'repository:install',
                '--server='.$serverId,
                '--site='.($ploiSiteId ?? $this->site->domain),
                '--no-interaction',
            ]);

            if (! $repoResult['success']) {
                Log::warning("Repository installation failed: {$repoResult['output']}");
                // Continue anyway - the user can install the repo manually
            }

            // Step 3: Create database if specified
            if ($this->site->database_name) {
                $dbResult = $this->runPloiCommand([
                    'database:create',
                    '--server='.$serverId,
                    '--name='.$this->site->database_name,
                    '--site_id='.($ploiSiteId ?? ''),
                    '--no-interaction',
                ]);

                if (! $dbResult['success']) {
                    Log::warning("Database creation failed: {$dbResult['output']}");
                    // Continue anyway - user can create database manually
                }
            }

            // Calculate the site path
            $path = $this->calculateSitePath();

            $this->site->markAsActive($path, $ploiSiteId);

            Log::info("Site provisioned successfully: {$this->site->domain}", [
                'site_id' => $this->site->id,
                'ploi_site_id' => $ploiSiteId,
                'path' => $path,
            ]);

        } catch (\Throwable $e) {
            Log::error("Site provisioning failed: {$e->getMessage()}", [
                'site_id' => $this->site->id,
                'exception' => $e,
            ]);

            $this->site->markAsFailed($e->getMessage());

            throw $e;
        }
    }

    /**
     * @param  array<string>  $arguments
     * @return array{success: bool, output: string}
     */
    protected function runPloiCommand(array $arguments): array
    {
        $command = array_filter(array_merge(['ploi'], $arguments));

        Log::debug('Running Ploi command', ['command' => implode(' ', $command)]);

        $result = Process::timeout(300)->run($command);

        return [
            'success' => $result->successful(),
            'output' => $result->output() ?: $result->errorOutput(),
        ];
    }

    protected function extractSiteId(string $output): ?string
    {
        // Try to extract site ID from Ploi CLI output
        // The format varies, so we try multiple patterns
        if (preg_match('/site\s+(?:id|ID):\s*(\d+)/i', $output, $matches)) {
            return $matches[1];
        }

        if (preg_match('/created\s+site\s+(\d+)/i', $output, $matches)) {
            return $matches[1];
        }

        if (preg_match('/\bsite[:\s]+(\d+)\b/i', $output, $matches)) {
            return $matches[1];
        }

        // Return null if we can't extract the ID - we'll use the domain instead
        return null;
    }

    protected function getSystemUser(): string
    {
        // Generate a system user based on the domain
        return preg_replace('/[^a-z0-9_]/', '_', strtolower(
            explode('.', $this->site->domain)[0]
        ));
    }

    protected function calculateSitePath(): string
    {
        if ($this->site->isolated_user) {
            $systemUser = $this->getSystemUser();

            return "/home/{$systemUser}/{$this->site->domain}";
        }

        return "/home/ploi/{$this->site->domain}";
    }
}
