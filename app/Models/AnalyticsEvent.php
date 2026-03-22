<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyticsEvent extends Model
{
    protected $fillable = [
        'user_id',
        'event_type',
        'provider_name',
        'repository_name',
        'messages_count',
        'tokens_in',
        'tokens_out',
        'cost_usd',
        'agent_seconds',
        'compaction_count',
        'ralph_iterations',
        'tool_usage',
        'task_created_at',
    ];

    protected function casts(): array
    {
        return [
            'tokens_in' => 'integer',
            'tokens_out' => 'integer',
            'cost_usd' => 'decimal:6',
            'agent_seconds' => 'integer',
            'compaction_count' => 'integer',
            'ralph_iterations' => 'integer',
            'tool_usage' => 'array',
            'task_created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Record analytics for a task before it's deleted.
     */
    public static function recordTaskDeletion(Task $task, int $userId): self
    {
        $messageStats = $task->messages()
            ->selectRaw('SUM(tokens_in) as tokens_in, SUM(tokens_out) as tokens_out, SUM(cost_usd) as cost, COUNT(*) as cnt')
            ->first();

        $toolCounts = [];
        $task->messages()->whereNotNull('tool_calls')->each(function ($msg) use (&$toolCounts) {
            foreach ($msg->tool_calls ?? [] as $call) {
                $name = $call['name'] ?? $call['tool'] ?? $call['type'] ?? 'unknown';
                $toolCounts[$name] = ($toolCounts[$name] ?? 0) + 1;
            }
        });

        $agentSeconds = 0;
        if ($task->started_at && $task->completed_at) {
            $agentSeconds = $task->completed_at->diffInSeconds($task->started_at);
        }

        return self::create([
            'user_id' => $userId,
            'event_type' => 'task_deleted',
            'provider_name' => $task->aiProvider?->display_name,
            'repository_name' => $task->repository?->name,
            'messages_count' => (int) ($messageStats->cnt ?? 0),
            'tokens_in' => (int) ($messageStats->tokens_in ?? 0),
            'tokens_out' => (int) ($messageStats->tokens_out ?? 0),
            'cost_usd' => (float) ($messageStats->cost ?? 0),
            'agent_seconds' => $agentSeconds,
            'compaction_count' => (int) $task->compaction_count,
            'ralph_iterations' => (int) $task->ralph_iteration,
            'tool_usage' => ! empty($toolCounts) ? $toolCounts : null,
            'task_created_at' => $task->created_at,
        ]);
    }
}
