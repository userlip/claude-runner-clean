<?php

namespace App\Jobs;

use App\Enums\MessageRole;
use App\Enums\MessageStatus;
use App\Models\Message;
use App\Models\Task;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class CheckSubagentStatusJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300; // 5 minutes max per check

    public function __construct(
        public Task $task,
        public int $attempt = 1,
        public int $maxAttempts = 120
    ) {}

    public function handle(): void
    {
        $this->task->refresh();

        // If the task was already completed/failed (e.g. user stopped it), don't check
        if (! $this->task->has_active_subagents) {
            Log::debug('CheckSubagentStatusJob: subagents no longer active, skipping', [
                'task_id' => $this->task->id,
                'status' => $this->task->status->value,
            ]);

            return;
        }

        // If we've exceeded max attempts, mark as completed and move on
        if ($this->attempt > $this->maxAttempts) {
            Log::warning('CheckSubagentStatusJob: max attempts reached, marking completed', [
                'task_id' => $this->task->id,
                'attempts' => $this->attempt,
            ]);
            $this->task->markAsCompleted();

            return;
        }

        Log::info('CheckSubagentStatusJob: sending status check', [
            'task_id' => $this->task->id,
            'attempt' => $this->attempt,
        ]);

        // Clear the subagent flag BEFORE dispatching so the next RunClaudeMessageJob
        // goes through normal completion flow. If subagents are STILL running,
        // the tool call detection will re-set this flag.
        $this->task->update(['has_active_subagents' => false]);

        // Send a status check prompt via RunClaudeMessageJob
        $statusMessage = Message::create([
            'task_id' => $this->task->id,
            'role' => MessageRole::User,
            'status' => MessageStatus::Sent,
            'content' => 'Check the status of your active subagents or team. If all work is complete, provide a final summary of what was accomplished.',
        ]);

        RunClaudeMessageJob::dispatch($this->task, $statusMessage, continue: true);
    }
}
