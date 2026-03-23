<?php

use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function (User $user, int $id): bool {
    return (int) $user->id === $id;
});

/**
 * Private channel for per-user updates (e.g. RecentChatsUpdated).
 * Only the authenticated user matching the given ID may subscribe.
 */
Broadcast::channel('users.{userId}', function (User $user, int $userId): bool {
    return (int) $user->id === $userId;
});

/**
 * Private channel for per-task updates (e.g. TaskStatusUpdated, TaskChatUpdated).
 * Only the task owner may subscribe — ownership is via repository or direct user_id.
 */
Broadcast::channel('tasks.{taskUuid}', function (User $user, string $taskUuid): bool {
    $task = Task::where('uuid', $taskUuid)->first();

    if (! $task) {
        return false;
    }

    $ownedViaRepository = $task->repository && $task->repository->user_id === $user->id;
    $ownedDirectly = $task->user_id === $user->id;

    return $ownedViaRepository || $ownedDirectly;
});
