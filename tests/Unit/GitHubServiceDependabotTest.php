<?php

namespace Tests\Unit;

use App\Models\GitHubConnection;
use App\Services\GitHubService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GitHubServiceDependabotTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_dependabot_pull_requests(): void
    {
        $connection = GitHubConnection::factory()->create(['access_token' => 'token']);
        $service = new GitHubService($connection);

        Http::fake([
            'https://api.github.com/repos/*/pulls*' => Http::response([
                ['id' => 1, 'number' => 10, 'user' => ['login' => 'dependabot[bot]']],
                ['id' => 2, 'number' => 11, 'user' => ['login' => 'someone']],
            ]),
        ]);

        $prs = $service->fetchDependabotPullRequests('org/repo');

        $this->assertCount(1, $prs);
        $this->assertSame(10, $prs->first()['number']);
    }
}
