<?php

namespace Tests\Feature;

use App\Enums\MessageRole;
use App\Enums\MessageStatus;
use App\Enums\SecurityRunStatus;
use App\Jobs\RunCodexMessageJob;
use App\Models\AiProvider;
use App\Models\GitHubConnection;
use App\Models\Message;
use App\Models\Repository;
use App\Models\SecurityRun;
use App\Services\SecurityManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SecurityManagementServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_marks_pr_waiting_for_ci_when_pending(): void
    {
        $orchestrator = $this->ensureCodexProvider();
        config(['services.security_ai.orchestrator_provider_id' => $orchestrator->id]);

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

    public function test_posts_waiting_ci_message_when_pending(): void
    {
        $orchestrator = $this->ensureCodexProvider();
        config(['services.security_ai.orchestrator_provider_id' => $orchestrator->id]);
        Queue::fake();

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
                [
                    'id' => 1,
                    'number' => 10,
                    'title' => 'bump dep',
                    'html_url' => 'https://github.com/org/repo/pull/10',
                    'user' => ['login' => 'dependabot[bot]'],
                    'head' => ['sha' => 'abc'],
                ],
            ]),
            'https://api.github.com/repos/org/repo/commits/*/status' => Http::response(['state' => 'pending']),
        ]);

        app(SecurityManagementService::class)->processRepository($repo);

        $task = $repo->fresh()->securityTask;
        $this->assertNotNull($task);

        $message = Message::where('task_id', $task->id)->latest()->first();
        $this->assertNotNull($message);
        $this->assertSame(MessageRole::Assistant, $message->role);
        $this->assertSame(MessageStatus::Sent, $message->status);
        $this->assertStringContainsString('Waiting for CI', $message->content ?? '');

        Queue::assertNothingPushed();
    }

    public function test_dispatches_orchestrator_message_when_ci_succeeds(): void
    {
        $orchestrator = $this->ensureCodexProvider();
        config(['services.security_ai.orchestrator_provider_id' => $orchestrator->id]);
        Queue::fake();

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
                [
                    'id' => 1,
                    'number' => 10,
                    'title' => 'bump dep',
                    'html_url' => 'https://github.com/org/repo/pull/10',
                    'user' => ['login' => 'dependabot[bot]'],
                    'base' => ['ref' => 'main'],
                    'head' => ['sha' => 'abc'],
                ],
            ]),
            'https://api.github.com/repos/org/repo/commits/*/status' => Http::response(['state' => 'success']),
        ]);

        app(SecurityManagementService::class)->processRepository($repo);

        $task = $repo->fresh()->securityTask;
        $this->assertNotNull($task);

        $message = Message::where('task_id', $task->id)
            ->where('role', MessageRole::User)
            ->latest()
            ->first();

        $this->assertNotNull($message);
        $this->assertStringContainsString('Security Management orchestrator', $message->content ?? '');
        $this->assertStringContainsString('"number": 10', $message->content ?? '');

        Queue::assertPushed(RunCodexMessageJob::class);
    }

    protected function ensureCodexProvider(): AiProvider
    {
        return AiProvider::firstOrCreate(
            ['name' => 'codex'],
            AiProvider::factory()->codex()->make(['name' => 'codex'])->toArray()
        );
    }

    public function test_allows_research_when_no_checks_after_grace_period(): void
    {
        $orchestrator = $this->ensureCodexProvider();
        config([
            'services.security_ai.orchestrator_provider_id' => $orchestrator->id,
            'services.security_ai.no_checks_grace_minutes' => 30,
        ]);
        Queue::fake();

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
                [
                    'id' => 1,
                    'number' => 10,
                    'title' => 'bump dep',
                    'html_url' => 'https://github.com/org/repo/pull/10',
                    'user' => ['login' => 'dependabot[bot]'],
                    'base' => ['ref' => 'main'],
                    'head' => ['sha' => 'abc'],
                    'created_at' => now()->subHours(2)->toIso8601String(),
                ],
            ]),
            'https://api.github.com/repos/org/repo/commits/*/status' => Http::response([
                'state' => 'pending',
                'total_count' => 0,
                'statuses' => [],
            ]),
            'https://api.github.com/repos/org/repo/commits/*/check-runs' => Http::response([
                'total_count' => 0,
                'check_runs' => [],
            ]),
        ]);

        app(SecurityManagementService::class)->processRepository($repo);

        $this->assertDatabaseHas('security_runs', [
            'repository_id' => $repo->id,
            'github_pr_id' => 1,
            'status' => SecurityRunStatus::Researching->value,
        ]);

        Queue::assertPushed(RunCodexMessageJob::class);
    }
}
