<?php

namespace App\Events;

use App\Models\Task;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskChatUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Task $task,
        public readonly string $type = 'message_chunk',
    ) {}

    public function broadcastOn(): Channel
    {
        return new PrivateChannel('tasks.'.$this->task->uuid);
    }

    public function broadcastAs(): string
    {
        return 'TaskChatUpdated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'uuid' => $this->task->uuid,
            'type' => $this->type,
            'status' => $this->task->status->value,
        ];
    }
}
