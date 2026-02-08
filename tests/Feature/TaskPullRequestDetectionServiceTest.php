<?php

namespace Tests\Feature;

use App\Enums\MessageRole;
use App\Enums\MessageStatus;
use App\Models\Message;
use App\Models\Repository;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskPullRequestDetectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskPullRequestDetectionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_detects_pr_url_in_messages_and_stores_pr_monitor_metadata(): void
    {
        $user = User::factory()->create();
        $repo = Repository::factory()->create([
            'user_id' => $user->id,
            'full_name' => 'org/repo',
        ]);

        $task = Task::factory()->completed()->create([
            'user_id' => $user->id,
            'repository_id' => $repo->id,
            'session_metadata' => [],
        ]);

        Message::create([
            'task_id' => $task->id,
            'role' => MessageRole::Assistant,
            'status' => MessageStatus::Sent,
            'content' => 'Opened PR: https://github.com/org/repo/pull/123',
        ]);

        app(TaskPullRequestDetectionService::class)->detectAndStore($task);

        $task->refresh();
        $monitor = $task->session_metadata['pr_monitor'] ?? null;
        $this->assertIsArray($monitor);
        $this->assertTrue($monitor['active'] ?? false);
        $this->assertSame('org/repo', $monitor['repository_full_name'] ?? null);
        $this->assertSame(123, $monitor['pr_number'] ?? null);
        $this->assertSame('message_url', $monitor['detected_via'] ?? null);
    }
}
