<?php

namespace Tests\Feature\Console;

use App\Enums\MessageRole;
use App\Enums\MessageStatus;
use App\Jobs\RunClaudeMessageJob;
use App\Models\GitHubConnection;
use App\Models\Message;
use App\Models\Repository;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GitHubPollTaskPullRequestsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_posts_user_message_and_dispatches_when_ci_finishes(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        GitHubConnection::factory()->create(['user_id' => $user->id, 'access_token' => 'token']);

        $repo = Repository::factory()->create([
            'user_id' => $user->id,
            'full_name' => 'org/repo',
        ]);

        $task = Task::factory()->completed()->create([
            'user_id' => $user->id,
            'repository_id' => $repo->id,
            'session_metadata' => [
                'pr_monitor' => [
                    'active' => true,
                    'repository_full_name' => 'org/repo',
                    'pr_number' => 123,
                    'last_notified_sha' => null,
                ],
            ],
        ]);

        Http::fake([
            'https://api.github.com/repos/org/repo/pulls/123' => Http::response([
                'state' => 'open',
                'merged' => false,
                'head' => ['sha' => 'abc'],
            ]),
            'https://api.github.com/repos/org/repo/commits/abc/status' => Http::sequence()
                ->push(['state' => 'pending'])
                ->push(['state' => 'failure', 'statuses' => []]),
            // fetchCombinedStatus will call check-runs every poll, and the poller will
            // fetch check-runs again when CI finishes to show failing check names.
            'https://api.github.com/repos/org/repo/commits/abc/check-runs' => Http::sequence()
                ->push([
                    'total_count' => 1,
                    'check_runs' => [
                        ['name' => 'CI', 'status' => 'in_progress', 'conclusion' => null],
                    ],
                ])
                ->push([
                    'total_count' => 1,
                    'check_runs' => [
                        ['name' => 'CI', 'status' => 'completed', 'conclusion' => 'failure'],
                    ],
                ])
                ->push([
                    'total_count' => 1,
                    'check_runs' => [
                        ['name' => 'CI', 'status' => 'completed', 'conclusion' => 'failure'],
                    ],
                ]),
            'https://api.github.com/repos/org/repo/pulls/123/reviews' => Http::response([]),
            'https://api.github.com/repos/org/repo/issues/123/comments*' => Http::response([]),
        ]);

        // First poll: CI is still pending - no message should be posted.
        Artisan::call('github:poll-task-prs');
        $this->assertSame(0, $task->messages()->count());

        // Second poll: CI finished (failure) - a user message should be posted and the task resumed.
        Artisan::call('github:poll-task-prs');

        $this->assertSame(1, $task->messages()->count());

        $message = $task->messages()->firstOrFail();
        $this->assertSame(MessageRole::User, $message->role);
        $this->assertSame(MessageStatus::Sent, $message->status);
        $this->assertStringContainsString('checks in github ci have finished', strtolower($message->content ?? ''));

        Queue::assertPushed(RunClaudeMessageJob::class);

        $task->refresh();
        $monitor = $task->session_metadata['pr_monitor'] ?? [];
        $this->assertSame('abc', $monitor['last_notified_sha'] ?? null);
    }

    public function test_backfills_pr_monitor_from_messages_for_completed_tasks(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        GitHubConnection::factory()->create(['user_id' => $user->id, 'access_token' => 'token']);

        $repo = Repository::factory()->create([
            'user_id' => $user->id,
            'full_name' => 'org/repo',
        ]);

        $task = Task::factory()->completed()->create([
            'user_id' => $user->id,
            'repository_id' => $repo->id,
            'session_metadata' => [], // no pr_monitor yet
        ]);

        Message::create([
            'task_id' => $task->id,
            'role' => MessageRole::Assistant,
            'status' => MessageStatus::Sent,
            'content' => 'Created PR: https://github.com/org/repo/pull/123',
        ]);

        Http::fake([
            'https://api.github.com/repos/org/repo/pulls/123' => Http::response([
                'state' => 'open',
                'merged' => false,
                'head' => ['sha' => 'abc'],
            ]),
            'https://api.github.com/repos/org/repo/commits/abc/status' => Http::response([
                'state' => 'failure',
                'statuses' => [],
            ]),
            'https://api.github.com/repos/org/repo/commits/abc/check-runs' => Http::response([
                'total_count' => 1,
                'check_runs' => [
                    ['name' => 'CI', 'status' => 'completed', 'conclusion' => 'failure'],
                ],
            ]),
            'https://api.github.com/repos/org/repo/pulls/123/reviews' => Http::response([]),
            'https://api.github.com/repos/org/repo/issues/123/comments*' => Http::response([]),
        ]);

        Artisan::call('github:poll-task-prs');

        $task->refresh();
        $monitor = $task->session_metadata['pr_monitor'] ?? null;
        $this->assertIsArray($monitor);
        $this->assertTrue($monitor['active'] ?? false);
        $this->assertSame('org/repo', $monitor['repository_full_name'] ?? null);
        $this->assertSame(123, $monitor['pr_number'] ?? null);

        $latestUser = $task->messages()
            ->where('role', MessageRole::User)
            ->latest('id')
            ->first();
        $this->assertNotNull($latestUser);
        $this->assertStringContainsString('checks in github ci have finished', strtolower($latestUser->content ?? ''));

        Queue::assertPushed(RunClaudeMessageJob::class);
    }
}
