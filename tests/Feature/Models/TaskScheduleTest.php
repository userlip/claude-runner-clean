<?php

use App\Models\AiProvider;
use App\Models\Repository;
use App\Models\Task;
use App\Models\TaskSchedule;
use App\Models\User;

it('creates a task schedule with repository, user, and provider', function () {
    $user = User::factory()->create();
    $repository = Repository::factory()->create(['user_id' => $user->id]);
    $provider = AiProvider::factory()->create();

    $schedule = TaskSchedule::factory()->create([
        'user_id' => $user->id,
        'repository_id' => $repository->id,
        'ai_provider_id' => $provider->id,
        'cron_expression' => '*/5 * * * *',
        'prompt' => 'Run tests',
        'delete_after_minutes' => 30,
    ]);

    expect($schedule->user->is($user))->toBeTrue();
    expect($schedule->repository->is($repository))->toBeTrue();
    expect($schedule->aiProvider->is($provider))->toBeTrue();
    expect($schedule->is_active)->toBeTrue();
});

it('links tasks back to schedules', function () {
    $schedule = TaskSchedule::factory()->create();
    $task = Task::factory()->create([
        'repository_id' => $schedule->repository_id,
        'user_id' => $schedule->user_id,
        'task_schedule_id' => $schedule->id,
    ]);

    expect($task->taskSchedule->is($schedule))->toBeTrue();
});
