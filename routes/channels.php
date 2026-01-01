<?php

use App\Models\Task;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('task.{taskId}', function ($user, $taskId) {
    $task = Task::find($taskId);

    // Match TaskPolicy: allow if task has no owner or user owns it
    return $task && ($task->user_id === null || $user->id === $task->user_id);
});
