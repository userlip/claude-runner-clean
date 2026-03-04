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
use Illuminate\Support\Facades\Process;

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

        // Post "starting" message to chat (the live stream message is separate)
        $this->postChatMessage($this->buildStartMessage($state, $story));

        // 5. Build and execute AI prompt (streams output into a live chat message)
        try {
            $result = $this->executeClaude($state, $story);
        } catch (\Exception $e) {
            Log::error('Ralph execution crashed', [
                'task_id' => $this->task->id,
                'iteration' => $this->iteration,
                'error' => $e->getMessage(),
            ]);
            $this->handleExecutionFailure([
                'success' => false,
                'error' => $e->getMessage(),
                'duration' => 0,
            ], $story);

            return;
        }

        if (! $result['success']) {
            $this->handleExecutionFailure($result, $story);

            return;
        }

        // 6. Run verification
        $verificationPassed = $this->runVerification($ralph, $state, $story);

        // 7. Log activity
        try {
            $ralph->logActivity($this->task, [
                'iteration' => $this->iteration,
                'timestamp' => now()->toIso8601String(),
                'story' => $story['id'],
                'tokens_in' => $result['tokens_in'] ?? 0,
                'tokens_out' => $result['tokens_out'] ?? 0,
                'duration_seconds' => $result['duration'] ?? 0,
                'status' => $verificationPassed ? 'passed' : 'failed',
            ]);
        } catch (\Exception $e) {
            Log::warning('Ralph failed to log activity', ['error' => $e->getMessage()]);
        }

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

        // Update iteration counter (don't touch session_id — Ralph uses its own sessions via proc_open)
        $this->task->update([
            'ralph_iteration' => $this->iteration,
        ]);

        // Rotate provider if configured
        $nextProvider = $this->task->getNextRalphProvider();
        if ($nextProvider) {
            $this->task->update(['ai_provider_id' => $nextProvider->id]);
        }
    }

    /**
     * Execute the selected AI provider to implement a user story, streaming output into a live chat message.
     *
     * @param  \App\DataObjects\RalphState  $state  The current Ralph state
     * @param  array<string, mixed>  $story  The user story to implement
     * @return array{success: bool, learnings?: string, tokens_in?: int, tokens_out?: int, duration?: int, error?: string}
     */
    protected function executeClaude(RalphState $state, array $story): array
    {
        $isCodex = $this->task->aiProvider?->isCodex() === true;
        $providerName = $isCodex ? 'Codex' : 'Claude';
        $prompt = $this->buildPrompt($state, $story);
        $command = $this->buildRalphCommand($prompt);
        $startTime = microtime(true);

        // Create a live message that will be updated as the model streams output
        $liveMessage = Message::create([
            'task_id' => $this->task->id,
            'role' => MessageRole::Assistant,
            'status' => MessageStatus::Sent,
            'content' => '',
        ]);
        $this->task->update(['last_message_at' => now()]);

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes, $this->task->workspace_path);

        if (! is_resource($process)) {
            $liveMessage->update(['content' => "*Failed to start {$providerName} process*"]);

            return [
                'success' => false,
                'error' => "Failed to start {$providerName} process",
                'duration' => 0,
            ];
        }

        if ($isCodex) {
            fwrite($pipes[0], $prompt."\n");
        }
        fclose($pipes[0]); // Close stdin

        // Set stream timeout (5 min of silence = hung)
        stream_set_timeout($pipes[1], 300);

        $tokensIn = 0;
        $tokensOut = 0;
        $learnings = '';
        $toolSummaries = [];
        $lastUpdateAt = 0;

        while (! feof($pipes[1])) {
            $line = fgets($pipes[1]);

            if ($line === false) {
                $meta = stream_get_meta_data($pipes[1]);
                if ($meta['timed_out']) {
                    Log::warning('Ralph Claude stream timed out', [
                        'task_id' => $this->task->id,
                        'iteration' => $this->iteration,
                    ]);
                    break;
                }

                continue;
            }

            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            $json = json_decode($line, true);
            if (! $json) {
                continue;
            }

            $type = $json['type'] ?? '';

            if ($isCodex) {
                if ($type === 'item.completed') {
                    $item = $json['item'] ?? [];
                    $itemType = $item['type'] ?? '';

                    if (in_array($itemType, ['agent_message', 'assistant_message'], true)) {
                        $text = $this->stripCitationMarkers($item['text'] ?? '');
                        $learnings .= $text."\n";

                        if (! empty(trim($text))) {
                            $this->postRalphTextMessage($text);
                        }
                    }

                    if ($itemType === 'command_execution') {
                        $toolSummaries[] = 'shell_command';
                    }
                }

                if ($type === 'turn.completed') {
                    $usage = $json['usage'] ?? [];
                    $tokensIn = ($usage['input_tokens'] ?? 0) + ($usage['cached_input_tokens'] ?? 0);
                    $tokensOut = $usage['output_tokens'] ?? $tokensOut;
                }
            } else {
                // Result message contains final stats
                if ($type === 'result') {
                    $tokensIn = $json['usage']['input_tokens'] ?? $tokensIn;
                    $tokensOut = $json['usage']['output_tokens'] ?? $tokensOut;
                }

                // Extract text content from assistant messages
                if ($type === 'assistant' && isset($json['message']['content'])) {
                    foreach ($json['message']['content'] as $block) {
                        if (($block['type'] ?? '') === 'text') {
                            $text = $block['text'] ?? '';
                            $learnings .= $text."\n";

                            // Create a separate chat message for each text block
                            if (! empty(trim($text))) {
                                $this->postRalphTextMessage($text);
                            }
                        }

                        if (($block['type'] ?? '') === 'tool_use') {
                            $toolName = $block['name'] ?? 'unknown';
                            $toolSummaries[] = $toolName;
                        }

                        if (($block['type'] ?? '') === 'tool_result') {
                            // Tool completed - don't need the full result in the stream
                        }
                    }
                }
            }

            // Update live status message every 3 seconds (avoid hammering DB)
            $now = microtime(true);
            if ($now - $lastUpdateAt >= 3) {
                $liveMessage->update([
                    'content' => $this->buildLiveContent($toolSummaries, $startTime),
                ]);
                $lastUpdateAt = $now;
            }
        }

        // Read stderr
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        $duration = (int) (microtime(true) - $startTime);

        // Final update to live message
        $liveMessage->update([
            'content' => $this->buildLiveContent($toolSummaries, $startTime, true),
            'tokens_in' => $tokensIn,
            'tokens_out' => $tokensOut,
        ]);

        if ($exitCode !== 0) {
            Log::error('Ralph execution failed', [
                'task_id' => $this->task->id,
                'iteration' => $this->iteration,
                'provider' => strtolower($providerName),
                'exit_code' => $exitCode,
                'error' => $stderr,
            ]);

            return [
                'success' => false,
                'error' => $stderr ?: "{$providerName} process failed with exit code {$exitCode}",
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
     * Build the live streaming content for the Ralph iteration message.
     * Shows text output and a summary of tool calls.
     */
    protected function buildLiveContent(array $toolSummaries, float $startTime, bool $finished = false): string
    {
        $elapsed = $this->formatDuration((int) (microtime(true) - $startTime));
        $status = $finished ? 'Finished' : 'Running';

        $content = "**Ralph [{$status}]** ({$elapsed})\n\n";

        if (! empty($toolSummaries)) {
            $toolCounts = array_count_values($toolSummaries);
            $toolParts = [];
            foreach ($toolCounts as $tool => $count) {
                $toolParts[] = $count > 1 ? "{$tool} x{$count}" : $tool;
            }
            $content .= '`'.implode(' · ', $toolParts).'`';
        }

        return $content;
    }

    /**
     * Build the CLI command to invoke Claude for a Ralph iteration.
     *
     * @param  string  $prompt  The prompt to send to Claude
     * @return string The full shell command
     */
    protected function buildRalphCommand(string $prompt): string
    {
        $provider = $this->task->aiProvider;

        if ($provider?->isCodex()) {
            $codexPath = config('services.codex.path', '/home/ploi/.npm-global/bin/codex');
            $workingDir = $this->task->working_directory;
            $model = $provider->model;

            $baseArgs = [
                escapeshellcmd($codexPath),
                'exec',
                '--json',
                '--skip-git-repo-check',
                '--dangerously-bypass-approvals-and-sandbox',
                '--cd',
                escapeshellarg($workingDir),
            ];

            if ($model) {
                $baseArgs[] = '-m';
                $baseArgs[] = escapeshellarg($model);
            }

            $baseArgs[] = '-';
            $agentCmd = implode(' ', $baseArgs);
        } else {
            $sessionId = escapeshellarg((string) str()->uuid());
            $escapedPrompt = escapeshellarg($prompt);

            $agentCmd = "/usr/bin/claude -p {$escapedPrompt} --output-format stream-json --verbose --dangerously-skip-permissions --session-id {$sessionId} --max-turns 50";

            // Add MCP servers (Playwright for browser automation)
            $mcpServers = [
                'playwright' => [
                    'command' => 'npx',
                    'args' => ['@playwright/mcp@latest'],
                ],
            ];
            $mcpConfig = json_encode(['mcpServers' => $mcpServers]);
            $agentCmd .= ' --mcp-config '.escapeshellarg($mcpConfig);
        }

        // Build isolated environment
        $envVars = [
            'HOME' => getenv('HOME') ?: '/home/ploi',
            'PATH' => '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin',
            'USER' => 'ploi',
            'SHELL' => '/bin/bash',
            'TERM' => 'xterm-256color',
        ];

        // Add provider API keys
        if ($provider) {
            foreach ($provider->getEnvironmentVariables() as $key => $value) {
                $envVars[$key] = $value;
            }
        }

        $envCmd = 'env -i';
        foreach ($envVars as $key => $value) {
            $envCmd .= ' '.escapeshellarg("{$key}={$value}");
        }

        return "{$envCmd} {$agentCmd}";
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

        try {
            // Run in workspace directory with generous timeout (10 minutes)
            $process = Process::path($this->task->workspace_path)
                ->timeout(1200)
                ->run($command);

            $passed = $process->successful();

            if (! $passed) {
                $ralph->appendProgress($this->task, "## Verification Failed\n\n```\n{$process->errorOutput()}\n```");
            }

            return $passed;
        } catch (\Illuminate\Process\Exceptions\ProcessTimedOutException $e) {
            Log::warning('Ralph verification timed out', [
                'task_id' => $this->task->id,
                'iteration' => $this->iteration,
                'command' => $command,
            ]);
            $ralph->appendProgress($this->task, "## Verification Timed Out\n\nCommand `{$command}` exceeded 20 minute timeout.");

            return false;
        } catch (\Exception $e) {
            Log::error('Ralph verification error', [
                'task_id' => $this->task->id,
                'iteration' => $this->iteration,
                'error' => $e->getMessage(),
            ]);
            $ralph->appendProgress($this->task, "## Verification Error\n\n```\n{$e->getMessage()}\n```");

            return false;
        }
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
            'ralph_stopped_reason' => 'completed',
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
     * Post a Ralph Claude text output message with a prefix for visual identification.
     */
    protected function postRalphTextMessage(string $text): void
    {
        $this->postChatMessage("**Ralph:** {$text}");
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

    protected function stripCitationMarkers(string $content): string
    {
        $content = preg_replace('/[\x{E200}-\x{E2FF}]+/u', '', $content);

        return preg_replace('/\s*citeturn\d+\w*\d*/i', '', $content);
    }
}
