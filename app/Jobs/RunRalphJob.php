<?php

namespace App\Jobs;

use App\DataObjects\RalphState;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Services\RalphWorkspaceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable as FoundationQueueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Process;

class RunRalphJob implements ShouldQueue
{
    use FoundationQueueable, Queueable;

    public int $timeout = 10800; // 3 hours

    public function __construct(
        public Task $task,
        public int $iteration = 1,
    ) {}

    public function handle(RalphWorkspaceService $ralph): void
    {
        Log::info('Ralph iteration started', [
            'task_id' => $this->task->id,
            'iteration' => $this->iteration,
        ]);

        // 1. Check if we should rotate
        if ($this->shouldRotate()) {
            $this->rotateContext();
        }

        // 2. Read state files
        try {
            $state = $ralph->readState($this->task);
        } catch (\Exception $e) {
            Log::error('Failed to read Ralph state', ['error' => $e->getMessage()]);
            $this->failWithError('Cannot read Ralph state files');

            return;
        }

        // 3. Check completion condition
        if ($state->allStoriesPassed()) {
            $this->completeTask();

            return;
        }

        // 4. Pick next story
        $story = $state->getNextStory();
        if (! $story) {
            $this->failWithError('No unpassed stories found');

            return;
        }

        // 5. Build and execute Claude prompt
        $result = $this->executeClaude($state, $story);

        if (! $result['success']) {
            $this->handleExecutionFailure($result);

            return;
        }

        // 6. Run verification
        $verificationPassed = $this->runVerification($story);

        // 7. Log activity
        $ralph->logActivity($this->task, [
            'iteration' => $this->iteration,
            'timestamp' => now()->toIso8601String(),
            'story' => $story['id'],
            'tokens_in' => $result['tokens_in'] ?? 0,
            'tokens_out' => $result['tokens_out'] ?? 0,
            'duration_seconds' => $result['duration'] ?? 0,
            'status' => $verificationPassed ? 'passed' : 'failed',
        ]);

        if ($verificationPassed) {
            // 8. Update prd.json
            $this->markStoryPassed($story);
            $ralph->updatePrd($this->task, $state->prd);

            // 9. Append learnings
            if (! empty($result['learnings'])) {
                $ralph->appendProgress($this->task, $result['learnings']);
            }
        }

        // 10. Check max iterations
        if ($this->task->ralph_max_iterations && $this->iteration >= $this->task->ralph_max_iterations) {
            $this->failWithError('max_iterations_reached');

            return;
        }

        // 11. Dispatch next iteration
        self::dispatch($this->task, $this->iteration + 1);
    }

    protected function shouldRotate(): bool
    {
        return $this->task->shouldRotateContext();
    }

    protected function rotateContext(): void
    {
        Log::info('Rotating Ralph context', [
            'task_id' => $this->task->id,
            'iteration' => $this->iteration,
        ]);

        // Start fresh Claude session
        $this->task->update([
            'session_id' => str()->uuid(),
            'ralph_iteration' => $this->iteration,
        ]);

        // Rotate provider if configured
        $nextProvider = $this->task->getNextRalphProvider();
        if ($nextProvider) {
            $this->task->update(['ai_provider_id' => $nextProvider->id]);
        }
    }

    protected function executeClaude(RalphState $state, array $story): array
    {
        // This is a simplified version - in production, use the actual Claude execution
        // from RunClaudeMessageJob with fresh context

        $prompt = $this->buildPrompt($state, $story);

        // For now, return mock result
        // TODO: Integrate with actual Claude CLI execution
        return [
            'success' => true,
            'learnings' => "## {$story['id']}\n- Implemented story\n- Files modified\n",
        ];
    }

    protected function buildPrompt(RalphState $state, array $story): string
    {
        return $state->prompt."\n\n".
            "## Current Story\n\n".
            "ID: {$story['id']}\n".
            "Title: {$story['title']}\n".
            "Criteria:\n".
            implode("\n", $story['acceptanceCriteria'] ?? [])."\n\n".
            "## Guardrails\n\n".
            $state->guardrails;
    }

    protected function runVerification(array $story): bool
    {
        // Get verification command from prd
        $ralph = app(RalphWorkspaceService::class);
        $state = $ralph->readState($this->task);
        $command = $state->prd['verificationCommand'] ?? 'php artisan test';

        // Run in workspace directory
        $process = Process::path($this->task->workspace_path)
            ->run($command);

        $passed = $process->successful();

        if (! $passed) {
            $ralph->appendProgress($this->task, "## Verification Failed\n\n```\n{$process->errorOutput()}\n```");
        }

        return $passed;
    }

    protected function markStoryPassed(array $story): void
    {
        $ralph = app(RalphWorkspaceService::class);
        $state = $ralph->readState($this->task);

        foreach ($state->prd['userStories'] as &$userStory) {
            if ($userStory['id'] === $story['id']) {
                $userStory['passes'] = true;
                break;
            }
        }

        $state->prd['userStories'] = collect($state->prd['userStories'])->values()->toArray();
        $ralph->updatePrd($this->task, $state->prd);
    }

    protected function completeTask(): void
    {
        $this->task->update([
            'status' => TaskStatus::Completed,
        ]);

        Log::info('Ralph task completed', ['task_id' => $this->task->id]);
    }

    protected function failWithError(string $reason): void
    {
        $this->task->update([
            'status' => TaskStatus::Failed,
        ]);

        Log::error('Ralph task failed', [
            'task_id' => $this->task->id,
            'reason' => $reason,
        ]);
    }

    protected function handleExecutionFailure(array $result): void
    {
        $ralph = app(RalphWorkspaceService::class);
        $ralph->appendProgress($this->task, "## Execution Failed\n\n".($result['error'] ?? 'Unknown error'));

        // Don't fail immediately - might recover on next iteration
        // But increment gutter count
        $this->task->increment('ralph_gutter_count');

        // If gutter count is high, pause
        if ($this->task->ralph_gutter_count >= 3) {
            $this->failWithError('gutter_detected');
        } else {
            // Try next iteration
            self::dispatch($this->task, $this->iteration + 1);
        }
    }
}
