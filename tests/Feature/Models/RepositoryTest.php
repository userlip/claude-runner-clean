<?php

use App\Models\Repository;
use App\Models\Task;
use App\Models\User;

test('repository belongs to user', function () {
    $repository = Repository::factory()->create();

    expect($repository->user)->toBeInstanceOf(User::class);
});

test('user has many repositories', function () {
    $user = User::factory()->create();
    Repository::factory()->count(3)->create(['user_id' => $user->id]);

    expect($user->repositories)->toHaveCount(3);
});

test('repository has unique github_id per user', function () {
    $user = User::factory()->create();
    Repository::factory()->create(['user_id' => $user->id, 'github_id' => 123]);

    expect(fn () => Repository::factory()->create(['user_id' => $user->id, 'github_id' => 123]))
        ->toThrow(Exception::class);
});

test('repository has many tasks', function () {
    $repository = Repository::factory()->create();
    $task = Task::factory()->create(['repository_id' => $repository->id]);

    expect($repository->tasks)->toHaveCount(1);
    expect($repository->tasks->first()->id)->toBe($task->id);
});
