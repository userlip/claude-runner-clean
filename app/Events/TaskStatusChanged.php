<?php

namespace App\Events;

use App\Models\Task;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Task $task
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('task.'.$this->task->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'task.status';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->task->id,
            'status' => $this->task->status->value,
            'is_compacting' => $this->task->is_compacting,
            'compaction_count' => $this->task->compaction_count,
            'init_status' => $this->task->init_status,
        ];
    }
}
