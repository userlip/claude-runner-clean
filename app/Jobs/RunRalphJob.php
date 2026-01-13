<?php

namespace App\Jobs;

use App\DataObjects\RalphState;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Services\RalphWorkspaceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable as FoundationQueueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Process;

class RunRalphJob implements ShouldQueue
{
    use FoundationQueueable;

    public int $timeout = 10800; // 3 hours

    private const int GUTTER_THRESHOLD = 3;

    private const int MAX_ITERATION_SAFEGUARD = 1000;

    public function __construct(
        public Task $task,
        public int $iteration = 1,
    ) {}

    public function handle(RalphWorkspaceService $ralph): void
    {
        // Prevent infinite loops
        if ($this->iteration > self::MAX_ITERATION_SAFEGUARD) {
            $this->failWithError('max_safeguard_iterations_exceeded');

            return;
        }

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
        $verificationPassed = $this->runVerification($ralph, $state, $story);

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
            $this->markStoryPassed($ralph, $state, $story);
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

    /**
     * Check if the context should be rotated for this iteration.
     *
     * @return bool True if context rotation is needed
     */
    protected function shouldRotate(): bool
    {
        return $this->task->shouldRotateContext();
    }

    /**
     * Rotate the Claude context by starting a new session and optionally switching providers.
     */
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

    /**
     * Execute the Claude AI to implement a user story.
     *
     * NOTE: This is currently a simplified stub implementation. The production version
     * should integrate with actual Claude CLI execution from RunClaudeMessageJob
     * with fresh context handling for each story implementation.
     *
     * @param  \App\DataObjects\RalphState  $state  The current Ralph state
     * @param  array<string, mixed>  $story  The user story to implement
     * @return array{success: bool, learnings?: string, tokens_in?: int, tokens_out?: int, duration?: int, error?: string}
     *
     * @todo Integrate with actual Claude CLI execution from RunClaudeMessageJob
     */
    protected function executeClaude(RalphState $state, array $story): array
    {
        // This is a simplified version - in production, use the actual Claude execution
        // from RunClaudeMessageJob with fresh context
        //
        // TODO: Implement actual Claude CLI execution with:
        // - Fresh context for each story
        // - Token tracking (tokens_in, tokens_out)
        // - Duration measurement
        // - Error handling
        // - Learning extraction

        $prompt = $this->buildPrompt($state, $story);

        // For now, return mock result
        // TODO: Integrate with actual Claude CLI execution
        return [
            'success' => true,
            'learnings' => "## {$story['id']}\n- Implemented story\n- Files modified\n",
        ];
    }

    /**
     * Build the Claude prompt for implementing a specific user story.
     *
     * @param  \App\DataObjects\RalphState  $state  The current Ralph state
     * @param  array<string, mixed>  $story  The user story
     * @return string The formatted prompt for Claude
     */
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

    /**
     * Run the verification command to check if the implementation passes.
     *
     * @param  \App\Services\RalphWorkspaceService  $ralph  The Ralph workspace service
     * @param  \App\DataObjects\RalphState  $state  The current Ralph state
     * @param  array<string, mixed>  $story  The user story being verified
     * @return bool True if verification passed
     */
    protected function runVerification(RalphWorkspaceService $ralph, RalphState $state, array $story): bool
    {
        // Get verification command from prd
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

    /**
     * Mark a user story as passed in the PRD.
     *
     * @param  \App\Services\RalphWorkspaceService  $ralph  The Ralph workspace service
     * @param  \App\DataObjects\RalphState  $state  The current Ralph state
     * @param  array<string, mixed>  $story  The user story to mark as passed
     */
    protected function markStoryPassed(RalphWorkspaceService $ralph, RalphState $state, array $story): void
    {
        foreach ($state->prd['userStories'] as &$userStory) {
            if ($userStory['id'] === $story['id']) {
                $userStory['passes'] = true;
                break;
            }
        }

        $state->prd['userStories'] = collect($state->prd['userStories'])->values()->toArray();
        $ralph->updatePrd($this->task, $state->prd);
    }

    /**
     * Complete the task when all stories have passed.
     */
    protected function completeTask(): void
    {
        $this->task->update([
            'status' => TaskStatus::Completed,
        ]);

        Log::info('Ralph task completed', ['task_id' => $this->task->id]);
    }

    /**
     * Fail the task with a specific reason.
     *
     * @param  string  $reason  The failure reason
     */
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

    /**
     * Handle Claude execution failure by logging and deciding whether to continue or fail.
     *
     * @param  array{success: bool, error?: string}  $result  The execution result
     */
    protected function handleExecutionFailure(array $result): void
    {
        $ralph = app(RalphWorkspaceService::class);
        $ralph->appendProgress($this->task, "## Execution Failed\n\n".($result['error'] ?? 'Unknown error'));

        // Don't fail immediately - might recover on next iteration
        // But increment gutter count
        $this->task->increment('ralph_gutter_count');

        // If gutter count is high, pause
        if ($this->task->ralph_gutter_count >= self::GUTTER_THRESHOLD) {
            $this->failWithError('gutter_detected');
        } else {
            // Try next iteration
            self::dispatch($this->task, $this->iteration + 1);
        }
    }
}
