<?php

use App\Jobs\RunClaudeMessageJob;
use App\Models\Message;
use App\Models\Site;
use App\Models\Task;

test('job constructor accepts task and message', function () {
    $site = Site::factory()->active()->create();
    $task = Task::factory()->create(['site_id' => $site->id]);
    $message = Message::factory()->user()->create([
        'task_id' => $task->id,
        'content' => 'echo "Hello"',
    ]);

    $job = new RunClaudeMessageJob($task, $message);

    expect($job->task->id)->toBe($task->id);
    expect($job->userMessage->content)->toBe('echo "Hello"');
    expect($job->continue)->toBeFalse();
});

test('job constructor accepts continue flag', function () {
    $site = Site::factory()->active()->create();
    $task = Task::factory()->create(['site_id' => $site->id]);
    $message = Message::factory()->user()->create(['task_id' => $task->id]);

    $job = new RunClaudeMessageJob($task, $message, continue: true);

    expect($job->continue)->toBeTrue();
});

test('buildCommand includes session id', function () {
    $site = Site::factory()->active()->create();
    $task = Task::factory()->create(['site_id' => $site->id]);
    $message = Message::factory()->user()->create([
        'task_id' => $task->id,
        'content' => 'test prompt',
    ]);

    $job = new RunClaudeMessageJob($task, $message);
    $command = $job->buildCommand();

    expect($command)->toContain('--session-id');
    expect($command)->toContain('--output-format stream-json');
    expect($command)->toContain('-p');
});

test('buildCommand includes max turns when set', function () {
    $site = Site::factory()->active()->create();
    $task = Task::factory()->withMaxTurns(5)->create(['site_id' => $site->id]);
    $message = Message::factory()->user()->create(['task_id' => $task->id]);

    $job = new RunClaudeMessageJob($task, $message);
    $command = $job->buildCommand();

    expect($command)->toContain('--max-turns 5');
});

test('buildCommand includes continue flag', function () {
    $site = Site::factory()->active()->create();
    $task = Task::factory()->create(['site_id' => $site->id]);
    $message = Message::factory()->user()->create(['task_id' => $task->id]);

    $job = new RunClaudeMessageJob($task, $message, continue: true);
    $command = $job->buildCommand();

    expect($command)->toContain('--continue');
});

test('buildCommand escapes prompt correctly', function () {
    $site = Site::factory()->active()->create();
    $task = Task::factory()->create(['site_id' => $site->id]);
    $message = Message::factory()->user()->create([
        'task_id' => $task->id,
        'content' => "Hello 'world' with \"quotes\"",
    ]);

    $job = new RunClaudeMessageJob($task, $message);
    $command = $job->buildCommand();

    // The prompt should be escaped - verify it's shell-safe
    expect($command)->toContain('-p ');
    // Should use escapeshellarg which adds single quotes
    expect($command)->not->toContain("Hello 'world'");
});

test('job has correct timeout', function () {
    $site = Site::factory()->active()->create();
    $task = Task::factory()->create(['site_id' => $site->id]);
    $message = Message::factory()->user()->create(['task_id' => $task->id]);

    $job = new RunClaudeMessageJob($task, $message);

    expect($job->timeout)->toBe(600);
});

test('job has single try', function () {
    $site = Site::factory()->active()->create();
    $task = Task::factory()->create(['site_id' => $site->id]);
    $message = Message::factory()->user()->create(['task_id' => $task->id]);

    $job = new RunClaudeMessageJob($task, $message);

    expect($job->tries)->toBe(1);
});
