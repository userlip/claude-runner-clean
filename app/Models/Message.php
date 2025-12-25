<?php

namespace App\Models;

use App\Enums\MessageRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id',
        'role',
        'content',
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
            'tool_calls' => 'array',
            'tokens_in' => 'integer',
            'tokens_out' => 'integer',
            'cost_usd' => 'decimal:6',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
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
