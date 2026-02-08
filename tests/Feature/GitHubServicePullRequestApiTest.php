<?php

namespace Tests\Feature;

use App\Models\GitHubConnection;
use App\Services\GitHubService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GitHubServicePullRequestApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_fetch_pull_requests_passes_query_params(): void
    {
        $connection = GitHubConnection::factory()->create(['access_token' => 'token']);
        $service = new GitHubService($connection);

        Http::fake([
            'https://api.github.com/repos/org/repo/pulls*' => Http::response([
                ['number' => 123],
            ]),
        ]);

        $prs = $service->fetchPullRequests('org/repo', [
            'state' => 'open',
            'head' => 'org:feature-branch',
        ]);

        $this->assertIsArray($prs);
        $this->assertSame(123, $prs[0]['number']);

        Http::assertSent(function ($request) {
            return str_starts_with($request->url(), 'https://api.github.com/repos/org/repo/pulls')
                && ($request['state'] ?? null) === 'open'
                && ($request['head'] ?? null) === 'org:feature-branch';
        });
    }

    public function test_fetch_check_runs_filters_ignored_runs(): void
    {
        $connection = GitHubConnection::factory()->create(['access_token' => 'token']);
        $service = new GitHubService($connection);

        Http::fake([
            'https://api.github.com/repos/org/repo/commits/*/check-runs' => Http::response([
                'total_count' => 2,
                'check_runs' => [
                    ['name' => 'CI', 'status' => 'completed', 'conclusion' => 'failure'],
                    ['name' => 'Claude Code Reviewer', 'status' => 'completed', 'conclusion' => 'failure'],
                ],
            ]),
        ]);

        $payload = $service->fetchCheckRuns('org/repo', 'abc');

        $this->assertSame(1, $payload['total_count']);
        $this->assertSame(1, $payload['ignored_total_count']);
        $this->assertCount(1, $payload['check_runs']);
        $this->assertSame('CI', $payload['check_runs'][0]['name']);
    }

    public function test_fetch_pull_request_reviews_returns_array(): void
    {
        $connection = GitHubConnection::factory()->create(['access_token' => 'token']);
        $service = new GitHubService($connection);

        Http::fake([
            'https://api.github.com/repos/org/repo/pulls/123/reviews' => Http::response([
                ['state' => 'CHANGES_REQUESTED', 'body' => 'Please fix tests.'],
            ]),
        ]);

        $reviews = $service->fetchPullRequestReviews('org/repo', 123);

        $this->assertIsArray($reviews);
        $this->assertSame('CHANGES_REQUESTED', $reviews[0]['state']);
    }

    public function test_fetch_issue_comments_passes_since_when_provided(): void
    {
        $connection = GitHubConnection::factory()->create(['access_token' => 'token']);
        $service = new GitHubService($connection);

        Http::fake([
            'https://api.github.com/repos/org/repo/issues/123/comments*' => Http::response([
                ['id' => 1, 'body' => 'hello'],
            ]),
        ]);

        $comments = $service->fetchIssueComments('org/repo', 123, '2026-02-08T00:00:00Z');

        $this->assertIsArray($comments);
        $this->assertSame(1, $comments[0]['id']);

        Http::assertSent(function ($request) {
            return str_starts_with($request->url(), 'https://api.github.com/repos/org/repo/issues/123/comments')
                && ($request['since'] ?? null) === '2026-02-08T00:00:00Z';
        });
    }
}
