<?php

use App\Enums\TaskStatus;
use App\Models\Site;
use App\Models\Task;

test('task belongs to site', function () {
    $task = Task::factory()->create();

    expect($task->site)->toBeInstanceOf(Site::class);
});

test('site has many tasks', function () {
    $site = Site::factory()->active()->create();
    Task::factory()->count(3)->create(['site_id' => $site->id]);

    expect($site->tasks)->toHaveCount(3);
});

test('task auto-generates uuid and session_id on create', function () {
    $site = Site::factory()->active()->create();
    $task = Task::create(['site_id' => $site->id]);

    expect($task->uuid)->not->toBeNull();
    expect($task->session_id)->not->toBeNull();
});

test('task uses uuid as route key', function () {
    $task = Task::factory()->create();

    expect($task->getRouteKeyName())->toBe('uuid');
});

test('can mark task as running', function () {
    $task = Task::factory()->create();

    $task->markAsRunning();

    expect($task->status)->toBe(TaskStatus::Running);
    expect($task->started_at)->not->toBeNull();
});

test('can mark task as completed', function () {
    $task = Task::factory()->running()->create();

    $task->markAsCompleted();

    expect($task->status)->toBe(TaskStatus::Completed);
    expect($task->completed_at)->not->toBeNull();
});
