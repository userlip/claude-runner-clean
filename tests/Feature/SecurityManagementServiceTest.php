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
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SecurityManagementServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_marks_pr_waiting_for_ci_when_pending(): void
    {
        $orchestrator = $this->ensureCodexProvider();
        config([
            'services.security_ai.orchestrator_provider_id' => $orchestrator->id,
            'services.security_ai.no_checks_grace_minutes' => 60,
        ]);

        $repo = Repository::factory()->create([
            'security_management_enabled' => true,
            'full_name' => 'org/repo',
        ]);
        GitHubConnection::factory()->create(['user_id' => $repo->user_id, 'access_token' => 'token']);
        $this->assertNotNull($repo->user->githubConnection);

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
        config([
            'services.security_ai.orchestrator_provider_id' => $orchestrator->id,
            'services.security_ai.no_checks_grace_minutes' => 60,
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
        $this->assertStringContainsString('"pr_number": 10', $message->content ?? '');

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

    public function test_orchestrator_prompt_is_minimal_with_repo_and_sha(): void
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
        $this->assertStringContainsString('"repo": "org/repo"', $message->content ?? '');
        $this->assertStringContainsString('"pr_number": 10', $message->content ?? '');
        $this->assertStringContainsString('"head_sha": "abc"', $message->content ?? '');
        $this->assertStringNotContainsString('"pull_request"', $message->content ?? '');
    }

    public function test_processes_researching_run_and_merges_when_allowed(): void
    {
        $orchestrator = $this->ensureCodexProvider();
        config(['services.security_ai.orchestrator_provider_id' => $orchestrator->id]);
        Process::fake();
        Queue::fake();

        $repo = Repository::factory()->create([
            'security_management_enabled' => true,
            'full_name' => 'org/repo',
            'ploi_server_id' => 55,
            'ploi_server_name' => 'test-server',
            'ploi_site_id' => 99,
            'ploi_site_domain' => 'test-site.example.com',
        ]);
        GitHubConnection::factory()->create(['user_id' => $repo->user_id, 'access_token' => 'token']);

        $run = SecurityRun::create([
            'repository_id' => $repo->id,
            'github_pr_id' => 1,
            'github_pr_number' => 10,
            'status' => SecurityRunStatus::Researching,
        ]);

        $service = app(SecurityManagementService::class);
        $task = $service->ensureSecurityTask($repo);

        Message::create([
            'task_id' => $task->id,
            'role' => MessageRole::User,
            'status' => MessageStatus::Sent,
            'content' => "Prompt\n```json\n{\"repo\":\"org/repo\",\"pr_number\":10,\"head_sha\":\"abc\"}\n```",
        ]);

        Message::create([
            'task_id' => $task->id,
            'role' => MessageRole::Assistant,
            'status' => MessageStatus::Sent,
            'content' => "Summary\n```json\n{\"merge_allowed\":true,\"risk_level\":\"low\"}\n```",
        ]);

        Http::fake(function ($request) {
            if ($request->url() === 'https://api.github.com/repos/org/repo/pulls/10/merge') {
                return Http::response(['sha' => 'merge-sha']);
            }

            if (str_starts_with($request->url(), 'https://api.github.com/repos/org/repo/pulls')) {
                return Http::response([]);
            }

            return Http::response([], 404);
        });

        $service->processRepository($repo->fresh());

        $this->assertDatabaseHas('security_runs', [
            'id' => $run->id,
            'status' => SecurityRunStatus::Deployed->value,
            'merge_commit_sha' => 'merge-sha',
        ]);

        Process::assertRan(function ($process) {
            return $process->command === [
                'ploi',
                'deploy',
                '--server=test-server',
                '--site=test-site.example.com',
                '--no-interaction',
            ];
        });
    }

    public function test_processes_researching_run_with_legacy_payload(): void
    {
        $orchestrator = $this->ensureCodexProvider();
        config(['services.security_ai.orchestrator_provider_id' => $orchestrator->id]);
        Process::fake();
        Queue::fake();

        $repo = Repository::factory()->create([
            'security_management_enabled' => true,
            'full_name' => 'org/repo',
            'ploi_server_id' => 55,
            'ploi_server_name' => 'test-server',
            'ploi_site_id' => 99,
            'ploi_site_domain' => 'test-site.example.com',
        ]);
        GitHubConnection::factory()->create(['user_id' => $repo->user_id, 'access_token' => 'token']);

        $run = SecurityRun::create([
            'repository_id' => $repo->id,
            'github_pr_id' => 1,
            'github_pr_number' => 10,
            'status' => SecurityRunStatus::Researching,
        ]);

        $service = app(SecurityManagementService::class);
        $task = $service->ensureSecurityTask($repo);

        Message::create([
            'task_id' => $task->id,
            'role' => MessageRole::User,
            'status' => MessageStatus::Sent,
            'content' => "Prompt\n```json\n{ \"merge_allowed\": true|false }\n```\n```json\n{\"pull_request\":{\"number\":10}}\n```",
        ]);

        Message::create([
            'task_id' => $task->id,
            'role' => MessageRole::Assistant,
            'status' => MessageStatus::Sent,
            'content' => "Summary\n```json\n{\"merge_allowed\":true,\"risk_level\":\"low\"}\n```",
        ]);

        Http::fake(function ($request) {
            if ($request->url() === 'https://api.github.com/repos/org/repo/pulls/10/merge') {
                return Http::response(['sha' => 'merge-sha']);
            }

            if (str_starts_with($request->url(), 'https://api.github.com/repos/org/repo/pulls')) {
                return Http::response([]);
            }

            return Http::response([], 404);
        });

        $service->processRepository($repo->fresh());

        $this->assertDatabaseHas('security_runs', [
            'id' => $run->id,
            'status' => SecurityRunStatus::Deployed->value,
            'merge_commit_sha' => 'merge-sha',
        ]);
    }

    public function test_major_update_with_real_risk_creates_user_needed_action(): void
    {
        $orchestrator = $this->ensureCodexProvider();
        config(['services.security_ai.orchestrator_provider_id' => $orchestrator->id]);
        Queue::fake();

        $repo = Repository::factory()->create([
            'security_management_enabled' => true,
            'full_name' => 'org/repo',
        ]);
        GitHubConnection::factory()->create(['user_id' => $repo->user_id, 'access_token' => 'token']);

        $run = SecurityRun::create([
            'repository_id' => $repo->id,
            'github_pr_id' => 1,
            'github_pr_number' => 10,
            'status' => SecurityRunStatus::Researching,
        ]);

        $service = app(SecurityManagementService::class);
        $task = $service->ensureSecurityTask($repo);

        Message::create([
            'task_id' => $task->id,
            'role' => MessageRole::User,
            'status' => MessageStatus::Sent,
            'content' => "Prompt\n```json\n{\"pull_request\":{\"number\":10,\"title\":\"security(deps): bump jquery from 3.7.1 to 4.0.0\"}}\n```",
        ]);

        // AI decides NOT to merge due to real security risk (merge_allowed: false)
        Message::create([
            'task_id' => $task->id,
            'role' => MessageRole::Assistant,
            'status' => MessageStatus::Sent,
            'content' => "Summary\n```json\n{\"merge_allowed\":false,\"risk_level\":\"high\",\"rationale\":\"This update introduces a potential XSS vulnerability\",\"breaking_changes\":\"Major changes with security implications\"}\n```",
        ]);

        Http::fake([
            'https://api.github.com/repos/org/repo/pulls*' => Http::response([]),
        ]);

        $service->processRepository($repo->fresh());

        $this->assertDatabaseHas('security_runs', [
            'id' => $run->id,
            'status' => SecurityRunStatus::NeedsUserAction->value,
        ]);

        $this->assertDatabaseHas('proposals', [
            'title' => 'User action needed: major dependency update (PR #10)',
            'status' => 'pending',
            'project' => 'User needed actions',
        ]);
    }

    public function test_major_update_without_security_risk_merges_automatically(): void
    {
        $orchestrator = $this->ensureCodexProvider();
        config(['services.security_ai.orchestrator_provider_id' => $orchestrator->id]);
        Process::fake();
        Queue::fake();

        $repo = Repository::factory()->create([
            'security_management_enabled' => true,
            'full_name' => 'org/repo',
            'ploi_server_id' => 55,
            'ploi_server_name' => 'test-server',
            'ploi_site_id' => 99,
            'ploi_site_domain' => 'test-site.example.com',
        ]);
        GitHubConnection::factory()->create(['user_id' => $repo->user_id, 'access_token' => 'token']);

        $run = SecurityRun::create([
            'repository_id' => $repo->id,
            'github_pr_id' => 1,
            'github_pr_number' => 10,
            'status' => SecurityRunStatus::Researching,
        ]);

        $service = app(SecurityManagementService::class);
        $task = $service->ensureSecurityTask($repo);

        Message::create([
            'task_id' => $task->id,
            'role' => MessageRole::User,
            'status' => MessageStatus::Sent,
            'content' => "Prompt\n```json\n{\"pull_request\":{\"number\":10,\"title\":\"security(deps): bump actions/upload-artifact from 4 to 6\"}}\n```",
        ]);

        // AI decides to merge because "breaking changes" are just tooling changes, not security risks
        Message::create([
            'task_id' => $task->id,
            'role' => MessageRole::Assistant,
            'status' => MessageStatus::Sent,
            'content' => "Summary\n```json\n{\"merge_allowed\":true,\"risk_level\":\"low\",\"rationale\":\"Major update but only requires Node 20 and runner 2.327 - not a security risk\",\"breaking_changes\":\"Node.js 24 runtime, requires Actions Runner 2.327.1\"}\n```",
        ]);

        Http::fake(function ($request) {
            if ($request->url() === 'https://api.github.com/repos/org/repo/pulls/10/merge') {
                return Http::response(['sha' => 'merge-sha']);
            }

            if (str_starts_with($request->url(), 'https://api.github.com/repos/org/repo/pulls')) {
                return Http::response([]);
            }

            return Http::response([], 404);
        });

        $service->processRepository($repo->fresh());

        // Should be deployed, not needs_user_action
        $this->assertDatabaseHas('security_runs', [
            'id' => $run->id,
            'status' => SecurityRunStatus::Deployed->value,
            'merge_commit_sha' => 'merge-sha',
        ]);

        // Should NOT create a proposal
        $this->assertDatabaseMissing('proposals', [
            'project' => 'User needed actions',
        ]);
    }

    public function test_ignore_action_closes_pr_without_escalating(): void
    {
        $orchestrator = $this->ensureCodexProvider();
        config(['services.security_ai.orchestrator_provider_id' => $orchestrator->id]);
        Queue::fake();

        $repo = Repository::factory()->create([
            'security_management_enabled' => true,
            'full_name' => 'org/repo',
        ]);
        GitHubConnection::factory()->create(['user_id' => $repo->user_id, 'access_token' => 'token']);

        $run = SecurityRun::create([
            'repository_id' => $repo->id,
            'github_pr_id' => 1,
            'github_pr_number' => 10,
            'status' => SecurityRunStatus::Researching,
        ]);

        $service = app(SecurityManagementService::class);
        $task = $service->ensureSecurityTask($repo);

        Message::create([
            'task_id' => $task->id,
            'role' => MessageRole::User,
            'status' => MessageStatus::Sent,
            'content' => "Prompt\n```json\n{\"pull_request\":{\"number\":10,\"title\":\"Bump actions/upload-artifact from 4 to 6\"}}\n```",
        ]);

        // AI decides to IGNORE - CI fails, no real security risk
        Message::create([
            'task_id' => $task->id,
            'role' => MessageRole::Assistant,
            'status' => MessageStatus::Sent,
            'content' => "Summary\n```json\n{\"merge_allowed\":false,\"action\":\"ignore\",\"risk_level\":\"low\",\"rationale\":\"Requires Node 20 and Actions Runner 2.327 - not a security risk, just tooling. CI fails. Site works fine, close the PR.\",\"ci_status\":\"failing\"}\n```",
        ]);

        Http::fake([
            'https://api.github.com/repos/org/repo/issues/10/comments' => Http::response(['id' => 1]),
            'https://api.github.com/repos/org/repo/pulls*' => Http::response([]),
        ]);

        $service->processRepository($repo->fresh());

        // Should be closed, not needs_user_action
        $this->assertDatabaseHas('security_runs', [
            'id' => $run->id,
            'status' => SecurityRunStatus::Closed->value,
        ]);

        // Should NOT create a proposal
        $this->assertDatabaseMissing('proposals', [
            'project' => 'User needed actions',
        ]);

        // Should have posted @dependabot close comment
        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.github.com/repos/org/repo/issues/10/comments'
                && str_contains($request->data()['body'], '@dependabot close');
        });
    }
}
