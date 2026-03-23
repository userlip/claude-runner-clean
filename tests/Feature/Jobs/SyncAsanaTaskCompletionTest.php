<?php

use App\Enums\TaskStatus;
use App\Jobs\SyncAsanaTaskCompletion;
use App\Models\Connection;
use App\Models\Repository;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

test('job is dispatched when task with asana_task_id is marked as completed', function () {
    Queue::fake();

    $user = User::factory()->create();
    $repository = Repository::factory()->create(['user_id' => $user->id]);
    $task = Task::factory()->create([
        'user_id' => $user->id,
        'repository_id' => $repository->id,
        'asana_task_id' => '1234567890',
        'status' => TaskStatus::Running,
        'started_at' => now(),
    ]);

    $task->markAsCompleted();

    Queue::assertPushed(SyncAsanaTaskCompletion::class, function ($job) use ($task) {
        return $job->task->id === $task->id;
    });
});

test('job is not dispatched when task without asana_task_id is completed', function () {
    Queue::fake();

    $task = Task::factory()->create([
        'asana_task_id' => null,
        'status' => TaskStatus::Running,
        'started_at' => now(),
    ]);

    $task->markAsCompleted();

    Queue::assertNotPushed(SyncAsanaTaskCompletion::class);
});

test('job adds comment to asana task with completion details', function () {
    Http::fake([
        'https://app.asana.com/api/1.0/tasks/1234567890/stories' => Http::response([
            'data' => ['gid' => 'story_123', 'type' => 'comment'],
        ], 201),
    ]);

    $user = User::factory()->create();
    Connection::factory()->asana()->create([
        'user_id' => $user->id,
        'credentials' => 'test_pat_token',
    ]);

    $task = Task::factory()->create([
        'user_id' => $user->id,
        'asana_task_id' => '1234567890',
        'title' => 'Test Task Title',
        'status' => TaskStatus::Completed,
    ]);

    $job = new SyncAsanaTaskCompletion($task);
    $job->handle();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://app.asana.com/api/1.0/tasks/1234567890/stories' &&
            str_contains($request['data']['text'], 'Code Review Completed') &&
            str_contains($request['data']['text'], 'Test Task Title');
    });
});

test('job extracts pr link from task title with pr number', function () {
    Http::fake([
        'https://app.asana.com/api/1.0/tasks/1234567890/stories' => Http::response([
            'data' => ['gid' => 'story_123', 'type' => 'comment'],
        ], 201),
    ]);

    $user = User::factory()->create();
    Connection::factory()->asana()->create([
        'user_id' => $user->id,
        'credentials' => 'test_pat_token',
    ]);

    $repository = Repository::factory()->create([
        'user_id' => $user->id,
        'full_name' => 'acme/widgets',
    ]);

    $task = Task::factory()->create([
        'user_id' => $user->id,
        'repository_id' => $repository->id,
        'asana_task_id' => '1234567890',
        'title' => 'Security PR #42 - Fix vulnerability',
        'status' => TaskStatus::Completed,
    ]);

    $job = new SyncAsanaTaskCompletion($task);
    $job->handle();

    Http::assertSent(function ($request) {
        return str_contains($request['data']['text'], 'https://github.com/acme/widgets/pull/42');
    });
});

test('job extracts pr link from task messages', function () {
    Http::fake([
        'https://app.asana.com/api/1.0/tasks/1234567890/stories' => Http::response([
            'data' => ['gid' => 'story_123', 'type' => 'comment'],
        ], 201),
    ]);

    $user = User::factory()->create();
    Connection::factory()->asana()->create([
        'user_id' => $user->id,
        'credentials' => 'test_pat_token',
    ]);

    $task = Task::factory()->create([
        'user_id' => $user->id,
        'asana_task_id' => '1234567890',
        'title' => 'Some task',
        'status' => TaskStatus::Completed,
    ]);

    \App\Models\Message::factory()->create([
        'task_id' => $task->id,
        'role' => \App\Enums\MessageRole::User,
        'content' => 'Please review this PR: https://github.com/acme/widgets/pull/123',
    ]);

    $job = new SyncAsanaTaskCompletion($task);
    $job->handle();

    Http::assertSent(function ($request) {
        return str_contains($request['data']['text'], 'https://github.com/acme/widgets/pull/123');
    });
});

test('job moves task to configured testing section', function () {
    Http::fake([
        'https://app.asana.com/api/1.0/tasks/1234567890/stories' => Http::response([
            'data' => ['gid' => 'story_123'],
        ], 201),
        'https://app.asana.com/api/1.0/sections/section_123/addTask' => Http::response([
            'data' => ['gid' => '1234567890'],
        ], 200),
    ]);

    $user = User::factory()->create();
    Connection::factory()->asana()->create([
        'user_id' => $user->id,
        'credentials' => 'test_pat_token',
    ]);

    $repository = Repository::factory()->create([
        'user_id' => $user->id,
        'asana_project_id' => 'project_123',
        'asana_testing_section_id' => 'section_123',
    ]);

    $task = Task::factory()->create([
        'user_id' => $user->id,
        'repository_id' => $repository->id,
        'asana_task_id' => '1234567890',
        'status' => TaskStatus::Completed,
    ]);

    $job = new SyncAsanaTaskCompletion($task);
    $job->handle();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://app.asana.com/api/1.0/sections/section_123/addTask' &&
            $request['data']['task'] === '1234567890';
    });
});

test('job auto-detects testing section when not configured', function () {
    Http::fake([
        'https://app.asana.com/api/1.0/tasks/1234567890/stories' => Http::response([
            'data' => ['gid' => 'story_123'],
        ], 201),
        'https://app.asana.com/api/1.0/projects/project_123/sections' => Http::response([
            'data' => [
                ['gid' => 'sec_1', 'name' => 'To Do'],
                ['gid' => 'sec_2', 'name' => 'In Progress'],
                ['gid' => 'sec_3', 'name' => 'Ready for Testing'],
                ['gid' => 'sec_4', 'name' => 'Done'],
            ],
        ], 200),
        'https://app.asana.com/api/1.0/sections/sec_3/addTask' => Http::response([
            'data' => ['gid' => '1234567890'],
        ], 200),
    ]);

    $user = User::factory()->create();
    Connection::factory()->asana()->create([
        'user_id' => $user->id,
        'credentials' => 'test_pat_token',
    ]);

    $repository = Repository::factory()->create([
        'user_id' => $user->id,
        'asana_project_id' => 'project_123',
        'asana_testing_section_id' => null, // Not configured
    ]);

    $task = Task::factory()->create([
        'user_id' => $user->id,
        'repository_id' => $repository->id,
        'asana_task_id' => '1234567890',
        'status' => TaskStatus::Completed,
    ]);

    $job = new SyncAsanaTaskCompletion($task);
    $job->handle();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://app.asana.com/api/1.0/sections/sec_3/addTask';
    });
});

test('job auto-detects testing section with qa naming', function () {
    Http::fake([
        'https://app.asana.com/api/1.0/tasks/1234567890/stories' => Http::response([
            'data' => ['gid' => 'story_123'],
        ], 201),
        'https://app.asana.com/api/1.0/projects/project_123/sections' => Http::response([
            'data' => [
                ['gid' => 'sec_1', 'name' => 'Backlog'],
                ['gid' => 'sec_2', 'name' => 'QA Review'],
            ],
        ], 200),
        'https://app.asana.com/api/1.0/sections/sec_2/addTask' => Http::response([
            'data' => ['gid' => '1234567890'],
        ], 200),
    ]);

    $user = User::factory()->create();
    Connection::factory()->asana()->create([
        'user_id' => $user->id,
        'credentials' => 'test_pat_token',
    ]);

    $repository = Repository::factory()->create([
        'user_id' => $user->id,
        'asana_project_id' => 'project_123',
        'asana_testing_section_id' => null,
    ]);

    $task = Task::factory()->create([
        'user_id' => $user->id,
        'repository_id' => $repository->id,
        'asana_task_id' => '1234567890',
        'status' => TaskStatus::Completed,
    ]);

    $job = new SyncAsanaTaskCompletion($task);
    $job->handle();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://app.asana.com/api/1.0/sections/sec_2/addTask';
    });
});

test('job gracefully skips section move when no testing section found', function () {
    Http::fake([
        'https://app.asana.com/api/1.0/tasks/1234567890/stories' => Http::response([
            'data' => ['gid' => 'story_123'],
        ], 201),
        'https://app.asana.com/api/1.0/projects/project_123/sections' => Http::response([
            'data' => [
                ['gid' => 'sec_1', 'name' => 'To Do'],
                ['gid' => 'sec_2', 'name' => 'Done'],
            ],
        ], 200),
    ]);

    $user = User::factory()->create();
    Connection::factory()->asana()->create([
        'user_id' => $user->id,
        'credentials' => 'test_pat_token',
    ]);

    $repository = Repository::factory()->create([
        'user_id' => $user->id,
        'asana_project_id' => 'project_123',
        'asana_testing_section_id' => null,
    ]);

    $task = Task::factory()->create([
        'user_id' => $user->id,
        'repository_id' => $repository->id,
        'asana_task_id' => '1234567890',
        'status' => TaskStatus::Completed,
    ]);

    $job = new SyncAsanaTaskCompletion($task);
    $job->handle();

    // Should not attempt to move task when no testing section found
    Http::assertNotSent(function ($request) {
        return str_contains($request->url(), '/addTask');
    });
});

test('job skips when task has no user', function () {
    Http::fake();

    // Create task with user_id = null to simulate no user
    $task = Task::factory()->create([
        'user_id' => null,
        'asana_task_id' => '1234567890',
    ]);

    // Verify user is actually null
    expect($task->user)->toBeNull();

    $job = new SyncAsanaTaskCompletion($task);
    $job->handle();

    Http::assertNothingSent();
});

test('job skips when user has no asana connection', function () {
    Http::fake();

    $user = User::factory()->create();
    // No Asana connection created

    $task = Task::factory()->create([
        'user_id' => $user->id,
        'asana_task_id' => '1234567890',
    ]);

    $job = new SyncAsanaTaskCompletion($task);
    $job->handle();

    Http::assertNothingSent();
});

test('job has retry configuration', function () {
    $task = Task::factory()->create(['asana_task_id' => '123']);
    $job = new SyncAsanaTaskCompletion($task);

    expect($job->tries)->toBe(3)
        ->and($job->backoff)->toBe(60);
});

test('job throws exception when comment fails to allow retry', function () {
    Http::fake([
        'https://app.asana.com/api/1.0/tasks/1234567890/stories' => Http::response([
            'errors' => [
                ['message' => 'Task not found'],
            ],
        ], 404),
    ]);

    $user = User::factory()->create();
    Connection::factory()->asana()->create([
        'user_id' => $user->id,
        'credentials' => 'test_pat_token',
    ]);

    $task = Task::factory()->create([
        'user_id' => $user->id,
        'asana_task_id' => '1234567890',
    ]);

    $job = new SyncAsanaTaskCompletion($task);

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('Failed to add comment to Asana task');

    $job->handle();
});

test('job does not throw when section move fails but comment succeeds', function () {
    Http::fake([
        'https://app.asana.com/api/1.0/tasks/1234567890/stories' => Http::response([
            'data' => ['gid' => 'story_123'],
        ], 201),
        'https://app.asana.com/api/1.0/sections/section_123/addTask' => Http::response([
            'errors' => [
                ['message' => 'Section not found'],
            ],
        ], 404),
    ]);

    $user = User::factory()->create();
    Connection::factory()->asana()->create([
        'user_id' => $user->id,
        'credentials' => 'test_pat_token',
    ]);

    $repository = Repository::factory()->create([
        'user_id' => $user->id,
        'asana_project_id' => 'project_123',
        'asana_testing_section_id' => 'section_123',
    ]);

    $task = Task::factory()->create([
        'user_id' => $user->id,
        'repository_id' => $repository->id,
        'asana_task_id' => '1234567890',
    ]);

    $job = new SyncAsanaTaskCompletion($task);

    // Should not throw - section move failure is graceful
    $job->handle();

    Http::assertSentCount(2); // Comment + section move attempt
});
