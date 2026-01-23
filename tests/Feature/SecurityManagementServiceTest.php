<?php

namespace Tests\Feature;

use App\Enums\SecurityRunStatus;
use App\Models\GitHubConnection;
use App\Models\Repository;
use App\Models\SecurityRun;
use App\Services\SecurityManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SecurityManagementServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_marks_pr_waiting_for_ci_when_pending(): void
    {
        $repo = Repository::factory()->create([
            'security_management_enabled' => true,
            'full_name' => 'org/repo',
        ]);
        GitHubConnection::factory()->create(['user_id' => $repo->user_id, 'access_token' => 'token']);

        SecurityRun::create([
            'repository_id' => $repo->id,
            'github_pr_id' => 1,
            'github_pr_number' => 10,
            'status' => SecurityRunStatus::Pending,
        ]);

        Http::fake([
            'https://api.github.com/repos/org/repo/pulls*' => Http::response([
                ['id' => 1, 'number' => 10, 'user' => ['login' => 'dependabot[bot]'], 'head' => ['sha' => 'abc']],
            ]),
            'https://api.github.com/repos/org/repo/commits/*/status' => Http::response(['state' => 'pending']),
        ]);

        app(SecurityManagementService::class)->processRepository($repo);

        $this->assertDatabaseHas('security_runs', [
            'repository_id' => $repo->id,
            'github_pr_id' => 1,
            'status' => SecurityRunStatus::WaitingCi->value,
        ]);
    }
}
