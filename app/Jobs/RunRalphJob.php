<?php

namespace App\Jobs;

use App\DataObjects\RalphState;
use App\Enums\MessageRole;
use App\Enums\MessageStatus;
use App\Enums\TaskStatus;
use App\Models\Message;
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

        // Update task iteration counter
        $this->task->update(['ralph_iteration' => $this->iteration]);

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
            $this->completeTask($state);

            return;
        }

        // 4. Pick next story
        $story = $state->getNextStory();
        if (! $story) {
            $this->failWithError('No unpassed stories found');

            return;
        }

        // Post "starting" message to chat
        $this->postChatMessage($this->buildStartMessage($state, $story));

        // 5. Build and execute Claude prompt
        $result = $this->executeClaude($state, $story);

        if (! $result['success']) {
            $this->handleExecutionFailure($result, $story);

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

        // Post iteration result to chat
        $this->postChatMessage($this->buildResultMessage($state, $story, $result, $verificationPassed));

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
     * @param  \App\DataObjects\RalphState  $state  The current Ralph state
     * @param  array<string, mixed>  $story  The user story to implement
     * @return array{success: bool, learnings?: string, tokens_in?: int, tokens_out?: int, duration?: int, error?: string}
     */
    protected function executeClaude(RalphState $state, array $story): array
    {
        $prompt = $this->buildPrompt($state, $story);
        $command = $this->buildRalphCommand($prompt);
        $startTime = microtime(true);

        $process = Process::path($this->task->workspace_path)
            ->timeout(3600)
            ->run($command);

        $duration = (int) (microtime(true) - $startTime);
        $output = $process->output();

        // Parse stream-json output for token usage
        $tokensIn = 0;
        $tokensOut = 0;
        $learnings = '';

        foreach (explode("\n", $output) as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            $json = json_decode($line, true);
            if (! $json) {
                continue;
            }

            // Result message contains final stats
            if (($json['type'] ?? '') === 'result') {
                $tokensIn = $json['usage']['input_tokens'] ?? $tokensIn;
                $tokensOut = $json['usage']['output_tokens'] ?? $tokensOut;
            }

            // Extract text content for learnings
            if (($json['type'] ?? '') === 'assistant' && isset($json['message']['content'])) {
                foreach ($json['message']['content'] as $block) {
                    if (($block['type'] ?? '') === 'text') {
                        $learnings .= $block['text']."\n";
                    }
                }
            }
        }

        if (! $process->successful()) {
            Log::error('Ralph Claude execution failed', [
                'task_id' => $this->task->id,
                'iteration' => $this->iteration,
                'exit_code' => $process->exitCode(),
                'error' => $process->errorOutput(),
            ]);

            return [
                'success' => false,
                'error' => $process->errorOutput() ?: 'Claude process failed with exit code '.$process->exitCode(),
                'tokens_in' => $tokensIn,
                'tokens_out' => $tokensOut,
                'duration' => $duration,
            ];
        }

        return [
            'success' => true,
            'learnings' => $learnings,
            'tokens_in' => $tokensIn,
            'tokens_out' => $tokensOut,
            'duration' => $duration,
        ];
    }

    /**
     * Build the CLI command to invoke Claude for a Ralph iteration.
     *
     * @param  string  $prompt  The prompt to send to Claude
     * @return string The full shell command
     */
    protected function buildRalphCommand(string $prompt): string
    {
        $sessionId = escapeshellarg((string) str()->uuid());
        $escapedPrompt = escapeshellarg($prompt);

        $claudeCmd = "/usr/bin/claude -p {$escapedPrompt} --output-format stream-json --verbose --dangerously-skip-permissions --session-id {$sessionId} --max-turns 50";

        // Add MCP servers (Playwright for browser automation)
        $mcpServers = [
            'playwright' => [
                'command' => 'npx',
                'args' => ['@playwright/mcp@latest'],
            ],
        ];
        $mcpConfig = json_encode(['mcpServers' => $mcpServers]);
        $claudeCmd .= ' --mcp-config '.escapeshellarg($mcpConfig);

        // Build isolated environment
        $envVars = [
            'HOME' => getenv('HOME') ?: '/home/ploi',
            'PATH' => '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin',
            'USER' => 'ploi',
            'SHELL' => '/bin/bash',
            'TERM' => 'xterm-256color',
        ];

        // Add provider API keys
        $provider = $this->task->aiProvider;
        if ($provider) {
            foreach ($provider->getEnvironmentVariables() as $key => $value) {
                $envVars[$key] = $value;
            }
        }

        $envCmd = 'env -i';
        foreach ($envVars as $key => $value) {
            $envCmd .= ' '.escapeshellarg("{$key}={$value}");
        }

        return "{$envCmd} {$claudeCmd}";
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
    protected function completeTask(?RalphState $state = null): void
    {
        $this->task->update([
            'status' => TaskStatus::Completed,
        ]);

        $storiesCount = $state ? count($state->prd['userStories'] ?? []) : '?';

        $this->postChatMessage(
            "## Ralph Complete\n\n".
            "All {$storiesCount} stories implemented and verified across {$this->iteration} iterations.\n\n".
            'Ready for **Manual QA**.'
        );

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
            'ralph_stopped_reason' => $reason,
        ]);

        $reasonLabels = [
            'max_iterations_reached' => 'Maximum iterations reached',
            'max_safeguard_iterations_exceeded' => 'Safety limit exceeded',
            'gutter_detected' => "Too many consecutive failures ({$this->task->ralph_gutter_count})",
        ];

        $label = $reasonLabels[$reason] ?? $reason;

        $this->postChatMessage(
            "## Ralph Stopped\n\n".
            "**Reason:** {$label}\n\n".
            "Iteration: {$this->iteration} | Gutter count: {$this->task->ralph_gutter_count}"
        );

        Log::error('Ralph task failed', [
            'task_id' => $this->task->id,
            'reason' => $reason,
        ]);
    }

    /**
     * Handle Claude execution failure by logging and deciding whether to continue or fail.
     *
     * @param  array{success: bool, error?: string}  $result  The execution result
     * @param  array<string, mixed>  $story  The story that failed
     */
    protected function handleExecutionFailure(array $result, array $story = []): void
    {
        $ralph = app(RalphWorkspaceService::class);
        $ralph->appendProgress($this->task, "## Execution Failed\n\n".($result['error'] ?? 'Unknown error'));

        // Don't fail immediately - might recover on next iteration
        // But increment gutter count
        $this->task->increment('ralph_gutter_count');

        $storyLabel = $story['id'] ?? 'unknown';
        $storyTitle = $story['title'] ?? '';
        $error = $result['error'] ?? 'Unknown error';
        $duration = $result['duration'] ?? 0;

        $this->postChatMessage(
            "## Ralph Iteration #{$this->iteration} — {$storyLabel}: {$storyTitle}\n\n".
            "**Status:** Execution failed\n".
            "**Duration:** {$this->formatDuration($duration)}\n".
            '**Error:** `'.mb_substr($error, 0, 200)."`\n".
            "**Gutter count:** {$this->task->ralph_gutter_count}/".self::GUTTER_THRESHOLD
        );

        // If gutter count is high, pause
        if ($this->task->ralph_gutter_count >= self::GUTTER_THRESHOLD) {
            $this->failWithError('gutter_detected');
        } else {
            // Try next iteration
            self::dispatch($this->task, $this->iteration + 1);
        }
    }

    /**
     * Post a message to the task's chat so the user can see Ralph progress.
     */
    protected function postChatMessage(string $content): void
    {
        Message::create([
            'task_id' => $this->task->id,
            'role' => MessageRole::Assistant,
            'status' => MessageStatus::Sent,
            'content' => $content,
        ]);

        $this->task->update(['last_message_at' => now()]);
    }

    /**
     * Build the chat message for the start of an iteration.
     *
     * @param  array<string, mixed>  $story
     */
    protected function buildStartMessage(RalphState $state, array $story): string
    {
        $passed = collect($state->prd['userStories'] ?? [])->filter(fn ($s) => $s['passes'] ?? false)->count();
        $total = count($state->prd['userStories'] ?? []);
        $ghIssue = isset($story['githubIssue']) ? " (#[{$story['githubIssue']}])" : '';

        return "## Ralph Iteration #{$this->iteration} — Starting\n\n".
            "**Story:** {$story['id']}: {$story['title']}{$ghIssue}\n".
            "**Progress:** {$passed}/{$total} stories passed\n".
            '**Status:** Running...';
    }

    /**
     * Build the chat message for the result of an iteration.
     *
     * @param  array<string, mixed>  $story
     * @param  array<string, mixed>  $result
     */
    protected function buildResultMessage(RalphState $state, array $story, array $result, bool $verificationPassed): string
    {
        $status = $verificationPassed ? 'Passed' : 'Failed verification';
        $statusIcon = $verificationPassed ? "\u{2705}" : "\u{274C}";
        $duration = $this->formatDuration($result['duration'] ?? 0);
        $tokensIn = number_format($result['tokens_in'] ?? 0);
        $tokensOut = number_format($result['tokens_out'] ?? 0);

        // Count stories passed (after potential update)
        $passed = collect($state->prd['userStories'] ?? [])->filter(fn ($s) => $s['passes'] ?? false)->count();
        if ($verificationPassed) {
            $passed++; // This story just passed
        }
        $total = count($state->prd['userStories'] ?? []);

        return "## Ralph Iteration #{$this->iteration} — {$story['id']}: {$story['title']}\n\n".
            "| | |\n|---|---|\n".
            "| Status | {$statusIcon} {$status} |\n".
            "| Duration | {$duration} |\n".
            "| Tokens | {$tokensIn} in / {$tokensOut} out |\n".
            "| Progress | {$passed}/{$total} stories |";
    }

    /**
     * Format seconds into a human-readable duration string.
     */
    protected function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return "{$seconds}s";
        }

        $minutes = intdiv($seconds, 60);
        $remaining = $seconds % 60;

        return "{$minutes}m {$remaining}s";
    }
}
