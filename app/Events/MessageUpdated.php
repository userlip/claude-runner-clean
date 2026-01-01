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
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'task_id' => $this->message->task_id,
            'role' => $this->message->role->value,
            'status' => $this->message->status->value,
            'content' => $this->message->content,
            'content_blocks' => $this->message->content_blocks,
            'tokens_in' => $this->message->tokens_in,
            'tokens_out' => $this->message->tokens_out,
            'cost_usd' => $this->message->cost_usd,
            'updated_at' => $this->message->updated_at->toISOString(),
            'html' => $this->message->isFromAssistant() ? $this->message->getFirstTextBlockHtml() : null,
            'grouped_blocks' => $this->message->isFromAssistant() ? $this->message->getGroupedBlocks() : null,
        ];
    }
}
