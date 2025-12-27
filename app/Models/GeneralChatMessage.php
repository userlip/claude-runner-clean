<?php

namespace App\Models;

use App\Enums\MessageRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeneralChatMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'general_chat_id',
        'role',
        'content',
        'images',
        'content_blocks',
        'raw_output',
        'tool_calls',
        'tokens_in',
        'tokens_out',
        'cost_usd',
    ];

    protected function casts(): array
    {
        return [
            'role' => MessageRole::class,
            'images' => 'array',
            'content_blocks' => 'array',
            'tool_calls' => 'array',
            'tokens_in' => 'integer',
            'tokens_out' => 'integer',
            'cost_usd' => 'decimal:6',
        ];
    }

    public function addContentBlock(array $block): void
    {
        $blocks = $this->content_blocks ?? [];
        $blocks[] = $block;
        $this->update(['content_blocks' => $blocks]);
    }

    public function generalChat(): BelongsTo
    {
        return $this->belongsTo(GeneralChat::class);
    }

    public function chat(): BelongsTo
    {
        return $this->generalChat();
    }

    public function isFromUser(): bool
    {
        return $this->role === MessageRole::User;
    }

    public function isFromAssistant(): bool
    {
        return $this->role === MessageRole::Assistant;
    }

    public function appendRawOutput(string $chunk): void
    {
        $this->update([
            'raw_output' => ($this->raw_output ?? '').$chunk,
        ]);
    }

    public function addToolCall(array $toolCall): void
    {
        $calls = $this->tool_calls ?? [];
        $calls[] = $toolCall;
        $this->update(['tool_calls' => $calls]);
    }
}
