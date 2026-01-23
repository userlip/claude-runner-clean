<?php

namespace Tests\Feature;

use App\Models\GitHubConnection;
use App\Models\Repository;
use App\Services\SecurityManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SecurityOrchestrationSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_orchestrator_creates_runs_for_dependabot_prs(): void
    {
        $repo = Repository::factory()->create([
            'security_management_enabled' => true,
            'full_name' => 'org/repo',
        ]);

        GitHubConnection::factory()->create([
            'user_id' => $repo->user_id,
            'access_token' => 'token',
        ]);

        Http::fake([
            'https://api.github.com/repos/org/repo/pulls*' => Http::response([
                ['id' => 123, 'number' => 5, 'user' => ['login' => 'dependabot[bot]'], 'head' => ['sha' => 'abc']],
            ]),
            'https://api.github.com/repos/org/repo/commits/*/status' => Http::response(['state' => 'pending']),
        ]);

        app(SecurityManagementService::class)->processRepository($repo);

        $this->assertDatabaseHas('security_runs', [
            'repository_id' => $repo->id,
            'github_pr_id' => 123,
        ]);
    }
}
