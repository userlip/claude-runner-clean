<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Message $message
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('task.'.$this->message->task_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.updated';
    }

    /**
     * Send minimal payload to avoid "Payload too large" errors.
     * Frontend will trigger Livewire refresh to get updated content.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'task_id' => $this->message->task_id,
            'role' => $this->message->role->value,
            'status' => $this->message->status->value,
            'tokens_in' => $this->message->tokens_in,
            'tokens_out' => $this->message->tokens_out,
            'updated_at' => $this->message->updated_at->toISOString(),
        ];
    }
}
