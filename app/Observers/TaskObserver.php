<?php

namespace App\Observers;

use App\Events\RecentChatsUpdated;
use App\Models\Task;

class TaskObserver
{
    /**
     * Broadcast a RecentChatsUpdated event whenever a task is created.
     */
    public function created(Task $task): void
    {
        RecentChatsUpdated::dispatch($task);
    }

    /**
     * Broadcast a RecentChatsUpdated event when a task title or status changes,
     * as these are the fields that affect the recent chats sidebar.
     */
    public function updated(Task $task): void
    {
        if ($task->wasChanged(['title', 'status'])) {
            RecentChatsUpdated::dispatch($task);
        }
    }
}
