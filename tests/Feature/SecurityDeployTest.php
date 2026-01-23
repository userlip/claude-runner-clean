<?php

namespace Tests\Feature;

use App\Models\Repository;
use App\Services\PloiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class SecurityDeployTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_site_id_when_missing(): void
    {
        Config::set('services.ploi.server_id', 'server-1');
        Config::set('services.ploi.server_name', 'server-name');

        $repo = Repository::factory()->create(['ploi_site_id' => null]);

        Process::fake(function ($process) {
            $command = is_array($process->command)
                ? implode(' ', $process->command)
                : $process->command;

            if ($command === 'ploi site:list --server=server-name --no-interaction') {
                return Process::result(
                    "| ID | Name | Domain | Type | Size | PHP | Repo |\n| 1 | example | example.marin.sh | laravel | 1 | 8.3 | Yes |"
                );
            }

            return Process::result('', 1);
        });

        $service = new PloiService;
        $siteId = $service->resolveSiteIdForRepository($repo, 'example.marin.sh');

        $this->assertSame('1', $siteId);
    }
}
