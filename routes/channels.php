<?php

use App\Models\Task;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('task.{taskId}', function ($user, $taskId) {
    $task = Task::find($taskId);

    return $task && $task->user_id === $user->id;
});
