<?php

namespace Tests\Feature;

use App\Models\GitHubConnection;
use App\Services\GitHubService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GitHubServiceIgnoreChecksTest extends TestCase
{
    use RefreshDatabase;

    public function test_ignores_claude_code_review_checks(): void
    {
        $connection = GitHubConnection::factory()->create(['access_token' => 'token']);
        $service = new GitHubService($connection);

        Http::fake([
            'https://api.github.com/repos/org/repo/commits/*/status' => Http::response([
                'state' => 'failure',
                'total_count' => 1,
                'statuses' => [
                    ['state' => 'failure', 'context' => 'Claude Code Reviewer'],
                ],
            ]),
            'https://api.github.com/repos/org/repo/commits/*/check-runs' => Http::response([
                'total_count' => 1,
                'check_runs' => [
                    ['status' => 'completed', 'conclusion' => 'failure', 'name' => 'Claude Code Reviewer'],
                ],
            ]),
        ]);

        $status = $service->fetchCombinedStatus('org/repo', 'abc');

        $this->assertSame('pending', $status['state']);
        $this->assertSame(0, $status['total_count']);
        $this->assertSame(0, $status['check_runs_total_count']);
    }
}
