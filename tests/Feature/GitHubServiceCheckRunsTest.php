<?php

namespace Tests\Feature;

use App\Models\GitHubConnection;
use App\Services\GitHubService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GitHubServiceCheckRunsTest extends TestCase
{
    use RefreshDatabase;

    public function test_combined_status_uses_check_runs_fallback(): void
    {
        $connection = GitHubConnection::factory()->create(['access_token' => 'token']);
        $service = new GitHubService($connection);

        Http::fake([
            'https://api.github.com/repos/org/repo/commits/*/status' => Http::response(['state' => 'pending']),
            'https://api.github.com/repos/org/repo/commits/*/check-runs' => Http::response([
                'total_count' => 2,
                'check_runs' => [
                    ['status' => 'completed', 'conclusion' => 'success'],
                    ['status' => 'completed', 'conclusion' => 'success'],
                ],
            ]),
        ]);

        $status = $service->fetchCombinedStatus('org/repo', 'abc');

        $this->assertSame('success', $status['state']);
        $this->assertSame('check_runs', $status['source']);
    }
}
