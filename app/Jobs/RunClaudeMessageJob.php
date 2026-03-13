<?php

namespace App\Jobs;

use App\Enums\MessageRole;
use App\Enums\MessageStatus;
use App\Enums\TaskStatus;
use App\Exceptions\RateLimitException;
use App\Models\Message;
use App\Models\Task;
use App\Services\TaskPullRequestDetectionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RunClaudeMessageJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 10800; // 3 hours for complex tasks

    public int $tries = 3; // Allow retries for rate limit errors

    /**
     * Backoff intervals in seconds for rate limit retries.
     * 30 minutes, then 1 hour, then 2 hours.
     *
     * @var array<int>
     */
    public array $backoff = [1800, 3600, 7200];

    public function __construct(
        public Task $task,
        public Message $userMessage,
        public bool $continue = false
    ) {}

    public function handle(): void
    {
        $this->task->markAsRunning();

        // If this is a continued session (like auto-compact), check for queued messages first
        // and include them in this run. This prevents messages from getting stuck in queue
        // if they were sent between jobs.
        if ($this->continue) {
            $this->includeQueuedMessagesInCurrentRun();
        }

        $assistantMessage = Message::create([
            'task_id' => $this->task->id,
            'role' => MessageRole::Assistant,
            'status' => MessageStatus::Sent,
            'content' => '',
        ]);

        $process = null;
        $pipes = [];
        $resultMetadata = null;

        try {
            $command = $this->buildCommand();
            $workingDir = $this->task->working_directory;

            if (! is_dir($workingDir)) {
                throw new \RuntimeException("Working directory does not exist: {$workingDir}. The repository may still be cloning.");
            }

            Log::info('Running Claude Code', [
                'task_id' => $this->task->id,
                'command' => $command,
                'working_dir' => $workingDir,
            ]);

            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];

            // Pass null for env to let the command handle environment isolation.
            // The command uses `env -i` to clear ALL inherited environment variables,
            // ensuring the workspace's .env file is used for database credentials.
            $process = proc_open($command, $descriptors, $pipes, $workingDir, null);

            if (! is_resource($process)) {
                throw new \RuntimeException('Failed to start Claude process');
            }

            stream_set_blocking($pipes[2], false);

            // For multimodal messages, write the JSON input to stdin
            if ($this->hasImages()) {
                $jsonInput = $this->buildStreamJsonInput()."\n";
                $bytesRemaining = strlen($jsonInput);
                $offset = 0;
                $chunkSize = 8192;

                while ($bytesRemaining > 0) {
                    $chunk = substr($jsonInput, $offset, $chunkSize);
                    $written = @fwrite($pipes[0], $chunk);

                    if ($written === false || $written === 0) {
                        $status = proc_get_status($process);
                        Log::error('Failed to write stream-json input to Claude stdin', [
                            'task_id' => $this->task->id,
                            'bytes_written' => $offset,
                            'bytes_total' => strlen($jsonInput),
                            'process_running' => $status['running'] ?? false,
                            'exit_code' => $status['exitcode'] ?? null,
                        ]);

                        break;
                    }

                    $offset += $written;
                    $bytesRemaining -= $written;
                }

                Log::debug('Sent stream-json input with images', [
                    'task_id' => $this->task->id,
                    'image_count' => count($this->userMessage->images ?? []),
                    'bytes_sent' => $offset,
                ]);
            }

            fclose($pipes[0]);

            // Set a read timeout on stdout so fgets() doesn't block forever
            // when the upstream API (e.g. Kimi) silently stops responding mid-stream.
            $streamTimeoutSeconds = 300; // 5 minutes of silence = hung
            stream_set_timeout($pipes[1], $streamTimeoutSeconds);

            $output = '';
            $toolCalls = [];
            $contentBlocks = [];
            $lastTurnUsage = null; // Track the last turn's context usage
            $askUserQuestionDetected = false; // Track if we need to wait for user input
            $minimalOutputCount = 0; // Track consecutive very short outputs (stuck loop detection)
            $rateLimitInfo = null; // Track rate limit errors for retry handling

            $resultReceived = false;
            while (! feof($pipes[1])) {
                $line = fgets($pipes[1]);
                if ($line === false) {
                    // Check if the stream timed out (no data for 5 minutes)
                    $meta = stream_get_meta_data($pipes[1]);
                    if ($meta['timed_out']) {
                        Log::error('Claude stdout stream timed out - API likely hung', [
                            'task_id' => $this->task->id,
                            'timeout_seconds' => $streamTimeoutSeconds,
                        ]);

                        break;
                    }

                    continue;
                }

                $output .= $line;
                $assistantMessage->appendRawOutput($line);

                $parsed = $this->parseLine($line);
                if ($parsed) {
                    if (isset($parsed['tool_call'])) {
                        $toolCalls[] = $parsed['tool_call'];
                        $contentBlocks[] = [
                            'type' => 'tool_use',
                            'tool' => $parsed['tool_call'],
                            'timestamp' => now()->toIso8601String(),
                        ];
                        $assistantMessage->update([
                            'tool_calls' => $toolCalls,
                            'content_blocks' => $contentBlocks,
                        ]);

                        // Detect AskUserQuestion tool - we need to IMMEDIATELY stop and wait for input
                        // In headless mode, Claude will auto-answer and continue if we don't stop now
                        if (($parsed['tool_call']['name'] ?? '') === 'AskUserQuestion') {
                            Log::info('AskUserQuestion detected - STOPPING to wait for user input', [
                                'task_id' => $this->task->id,
                                'tool_id' => $parsed['tool_call']['id'] ?? null,
                            ]);
                            $askUserQuestionDetected = true;

                            // IMMEDIATELY break out to stop Claude from auto-continuing
                            // The user must submit their response via the UI
                            break;
                        }
                    }
                    if (isset($parsed['content'])) {
                        $text = trim($parsed['content']);

                        // Detect stuck loop: consecutive very short outputs (e.g., "✓", "Done.", "Complete.")
                        // This happens when Claude gets stuck after heavy context compaction
                        if (strlen($text) <= 5) {
                            $minimalOutputCount++;
                            if ($minimalOutputCount >= 5) {
                                Log::warning('Detected stuck loop - forcing termination', [
                                    'task_id' => $this->task->id,
                                    'minimal_output_count' => $minimalOutputCount,
                                    'last_output' => $text,
                                ]);
                                break;
                            }
                        } else {
                            $minimalOutputCount = 0;
                        }

                        $contentBlocks[] = [
                            'type' => 'text',
                            'text' => $parsed['content'],
                            'timestamp' => now()->toIso8601String(),
                        ];
                        $assistantMessage->update([
                            'content' => ($assistantMessage->content ?? '').$parsed['content'],
                            'content_blocks' => $contentBlocks,
                        ]);
                    }
                    if (isset($parsed['turn_usage'])) {
                        // Track the latest turn's context usage (overwrites previous)
                        $lastTurnUsage = $parsed['turn_usage'];
                    }
                    if (isset($parsed['init_metadata'])) {
                        // Store session init metadata (model, MCP servers, skills, etc.)
                        $metadata = $this->task->session_metadata ?? [];
                        $metadata['init'] = $parsed['init_metadata'];
                        $this->task->update(['session_metadata' => $metadata]);
                        Log::debug('Session init metadata captured', [
                            'task_id' => $this->task->id,
                            'model' => $parsed['init_metadata']['model'] ?? null,
                            'mcp_servers_count' => count($parsed['init_metadata']['mcp_servers'] ?? []),
                            'skills_count' => count($parsed['init_metadata']['skills'] ?? []),
                        ]);
                    }
                    if (isset($parsed['subagent_started'])) {
                        $this->task->update([
                            'has_active_subagents' => true,
                            'last_message_at' => now(),
                        ]);
                    }
                    if (isset($parsed['result_metadata'])) {
                        // Store session result metadata (model usage, duration, turns)
                        $resultMetadata = $parsed['result_metadata'];
                        $metadata = $this->task->session_metadata ?? [];
                        $metadata['result'] = $resultMetadata;
                        $this->task->update(['session_metadata' => $metadata]);
                        Log::debug('Session result metadata captured', [
                            'task_id' => $this->task->id,
                            'duration_ms' => $resultMetadata['duration_ms'] ?? null,
                            'num_turns' => $resultMetadata['num_turns'] ?? null,
                        ]);
                    }
                    if (isset($parsed['compacting'])) {
                        Log::info('Context compaction started', [
                            'task_id' => $this->task->id,
                            'trigger' => $parsed['compacting']['trigger'],
                            'pre_tokens' => $parsed['compacting']['pre_tokens'],
                        ]);
                        $this->task->update(['is_compacting' => true, 'needs_compact' => false]);
                        $this->task->increment('compaction_count');
                    }
                    if (isset($parsed['context_low'])) {
                        Log::warning('Context low detected - needs compact', [
                            'task_id' => $this->task->id,
                        ]);
                        $this->task->update(['needs_compact' => true]);
                    }
                    if (isset($parsed['prompt_too_long'])) {
                        Log::error('Prompt too long - forcing compact', [
                            'task_id' => $this->task->id,
                        ]);
                        $this->task->update(['needs_compact' => true]);
                    }
                    if (isset($parsed['rate_limit'])) {
                        Log::warning('Rate limit detected from Claude API', [
                            'task_id' => $this->task->id,
                            'reset_time' => $parsed['rate_limit']['reset_time'] ?? 'unknown',
                            'message' => $parsed['rate_limit']['message'] ?? '',
                        ]);
                        $rateLimitInfo = $parsed['rate_limit'];
                        // Don't break immediately - let the process finish naturally
                        // We'll handle this after the loop
                    }
                    if (isset($parsed['usage'])) {
                        Log::info('Result event received - breaking loop', [
                            'task_id' => $this->task->id,
                            'last_turn_usage' => $lastTurnUsage,
                            'cost' => $parsed['usage']['cost_usd'] ?? null,
                        ]);

                        // Append the final result summary only if it's not already in the content
                        // (Claude often streams the final text AND includes it in the result event)
                        if (isset($parsed['result_summary'])) {
                            $currentContent = $assistantMessage->content ?? '';
                            $resultSummary = $parsed['result_summary'];

                            // Only append if the result summary isn't already at the end of content
                            if (! str_ends_with(trim($currentContent), trim($resultSummary))) {
                                $contentBlocks[] = [
                                    'type' => 'text',
                                    'text' => $resultSummary,
                                    'timestamp' => now()->toIso8601String(),
                                ];
                                $assistantMessage->update([
                                    'content' => $currentContent."\n\n".$resultSummary,
                                    'content_blocks' => $contentBlocks,
                                ]);
                            }
                        }

                        // Use last turn's context usage (actual context window usage)
                        // instead of cumulative session totals
                        $assistantMessage->update([
                            'tokens_in' => $lastTurnUsage['input_tokens'] ?? null,
                            'tokens_out' => $lastTurnUsage['output_tokens'] ?? null,
                            'cost_usd' => $parsed['usage']['cost_usd'] ?? null,
                        ]);

                        // Track provider usage with last turn's tokens
                        if ($this->task->aiProvider && $lastTurnUsage) {
                            $this->task->aiProvider->incrementUsage(
                                $lastTurnUsage['input_tokens'] ?? 0,
                                $lastTurnUsage['output_tokens'] ?? 0
                            );
                        }

                        // Clear compacting state when result is received
                        $this->task->update(['is_compacting' => false]);

                        // Result event received - Claude finished this response
                        $resultReceived = true;
                        break;
                    }
                }
            }

            // For resumed sessions, Claude process stays running - don't wait for it
            // Just close our pipes and mark as completed when we got the result
            $diagnostics = $this->captureProcessDiagnostics($process, $pipes, ! $resultReceived);
            $assistantMessage->storeExecutionDiagnostics(
                exitCode: $diagnostics['exit_code'],
                errorOutput: $diagnostics['error_output'],
                resultMetadata: $resultMetadata,
            );

            // Handle AskUserQuestion FIRST - we may have broken early before receiving result
            if ($askUserQuestionDetected) {
                $this->task->markAsWaitingForInput();
                Log::info('Task marked as waiting for input (AskUserQuestion detected)', [
                    'task_id' => $this->task->id,
                ]);

                $this->sendPushNotification(
                    'Input Required',
                    'Claude is waiting for your response to continue.',
                    true
                );

                // Don't process queued messages or auto-compact - wait for user input
                return;
            }

            // Handle rate limit errors BEFORE checking resultReceived
            // Rate limits can occur with or without a result event
            if ($rateLimitInfo !== null) {
                $resetTime = $rateLimitInfo['reset_time'] ?? null;
                $message = $rateLimitInfo['message'] ?? 'Rate limit exceeded';

                Log::warning('Rate limit detected - throwing exception for retry', [
                    'task_id' => $this->task->id,
                    'reset_time' => $resetTime,
                    'attempt' => $this->attempts(),
                    'max_tries' => $this->tries,
                ]);

                // Update the assistant message to show rate limit info
                $assistantMessage->update([
                    'content' => $message,
                ]);

                // Mark task as pending so it can be retried
                // Don't mark as completed or failed - the job will retry
                $this->task->update(['status' => \App\Enums\TaskStatus::Pending]);

                // Throw exception to trigger Laravel's retry mechanism with backoff
                throw new RateLimitException(
                    resetTime: $resetTime,
                    message: $message
                );
            }

            // If we tried to --resume but got 0 turns (empty response), the session is gone.
            // Retry as a fresh session instead of silently returning nothing.
            if ($resultReceived && $this->continue && empty(trim($assistantMessage->content ?? ''))) {
                $numTurns = $this->task->session_metadata['result']['num_turns'] ?? null;
                if ($numTurns === 0 || $numTurns === null) {
                    Log::warning('Resume returned empty response (0 turns) - retrying as fresh session', [
                        'task_id' => $this->task->id,
                        'old_session_id' => $this->task->session_id,
                    ]);

                    // Start a fresh session
                    $this->task->update(['session_id' => (string) Str::uuid()]);
                    $assistantMessage->delete();

                    // Re-dispatch as a new session (continue: false)
                    self::dispatch($this->task, $this->userMessage, continue: false);

                    return;
                }
            }

            if ($resultReceived && $this->handlePrematurePlanOnlyResult($assistantMessage, $toolCalls, $resultMetadata)) {
                return;
            }

            if ($resultReceived) {
                $this->task->markAsCompleted();
                Log::debug('Task marked as completed (result received)', ['task_id' => $this->task->id]);

                // Best-effort: if the agent opened a PR, store it so we can poll CI/reviews later.
                try {
                    app(TaskPullRequestDetectionService::class)->detectAndStore($this->task);
                } catch (\Throwable $e) {
                    Log::debug('PR detection failed (ignored)', [
                        'task_id' => $this->task->id,
                        'error' => $e->getMessage(),
                    ]);
                }

                $this->sendPushNotification(
                    'Task Completed',
                    $this->getNotificationBody($assistantMessage),
                    true
                );

                // Auto-compact if context is low
                $this->task->refresh();
                if ($this->task->needs_compact) {
                    Log::info('Auto-dispatching compact for low context', ['task_id' => $this->task->id]);
                    $this->dispatchCompact();
                }

                // Process any queued messages
                $this->processQueuedMessages();
            } else {
                // No result received - process exited unexpectedly or stream timed out
                Log::warning('Claude process ended without result event', [
                    'task_id' => $this->task->id,
                    'had_tool_calls' => ! empty($toolCalls),
                    'had_content' => ! empty($assistantMessage->content),
                    'output_length' => strlen($output),
                ]);

                // Clear compacting state if it was set
                $this->task->update(['is_compacting' => false]);

                $this->task->markAsCompleted();

                $this->sendPushNotification(
                    'Task Interrupted',
                    'Claude stopped responding (possible API timeout). You can send a follow-up message to continue.',
                    false
                );
            }

        } catch (RateLimitException $e) {
            // Rate limit exceptions should NOT mark the task as failed
            // They will be retried with backoff - just re-throw
            throw $e;
        } catch (\Throwable $e) {
            if (is_resource($process)) {
                $diagnostics = $this->captureProcessDiagnostics($process, $pipes, true);
                $assistantMessage->storeExecutionDiagnostics(
                    exitCode: $diagnostics['exit_code'],
                    errorOutput: $diagnostics['error_output'],
                    resultMetadata: $resultMetadata,
                );
            }

            Log::error("Claude execution failed: {$e->getMessage()}");

            $assistantMessage->update([
                'content' => "Error: {$e->getMessage()}",
            ]);

            $this->task->markAsFailed();

            // Best-effort PR detection on failures as well (agent may have created a PR before erroring).
            try {
                app(TaskPullRequestDetectionService::class)->detectAndStore($this->task);
            } catch (\Throwable $e) {
                Log::debug('PR detection failed (ignored)', [
                    'task_id' => $this->task->id,
                    'error' => $e->getMessage(),
                ]);
            }

            // Send push notification on failure
            $this->sendPushNotification(
                'Task Failed',
                "Error: {$e->getMessage()}",
                false
            );

            throw $e;
        }
    }

    /**
     * @param  resource  $process
     * @param  array<int, resource>  $pipes
     * @return array{exit_code: int|null, error_output: string|null}
     */
    protected function captureProcessDiagnostics($process, array $pipes, bool $terminateRunning): array
    {
        $errorOutput = '';

        if (isset($pipes[1]) && is_resource($pipes[1])) {
            fclose($pipes[1]);
        }

        if (isset($pipes[2]) && is_resource($pipes[2])) {
            $stderr = stream_get_contents($pipes[2]);
            if ($stderr !== false) {
                $errorOutput .= $stderr;
            }
            fclose($pipes[2]);
        }

        $status = proc_get_status($process);
        $wasRunning = $status['running'] ?? false;
        $exitCode = $wasRunning ? null : ($status['exitcode'] ?? null);

        if ($wasRunning && $terminateRunning) {
            proc_terminate($process);
        }

        $closedExitCode = proc_close($process);

        if ($exitCode === null && ($terminateRunning || ! $wasRunning) && $closedExitCode !== -1) {
            $exitCode = $closedExitCode;
        }

        return [
            'exit_code' => $exitCode,
            'error_output' => $errorOutput !== '' ? $errorOutput : null,
        ];
    }

    protected function sendPushNotification(string $title, string $body, bool $success): void
    {
        // Get user directly from task, or fall back to repository owner
        $user = $this->task->user ?? $this->task->repository?->user;

        Log::debug('sendPushNotification called', [
            'user_id' => $user?->id,
            'push_enabled' => $user?->push_notifications_enabled,
            'title' => $title,
        ]);

        if (! $user || ! $user->push_notifications_enabled) {
            Log::debug('Push notification skipped - user not found or push disabled');

            return;
        }

        $taskTitle = $this->task->title ?? ($this->task->isGeneralChat() ? 'Chat' : 'Untitled Task');
        $fullTitle = $success ? "Completed: {$taskTitle}" : "Failed: {$taskTitle}";

        SendPushNotificationJob::dispatch(
            $user,
            $fullTitle,
            Str::limit($body, 150),
            route('filament.admin.resources.tasks.chat', ['record' => $this->task->uuid]),
            [
                ['action' => 'view', 'title' => 'View'],
                ['action' => 'dismiss', 'title' => 'Dismiss'],
            ]
        );

        Log::debug('SendPushNotificationJob dispatched', ['title' => $fullTitle]);
    }

    protected function getNotificationBody(Message $message): string
    {
        $content = $message->content;

        if (empty($content) && ! empty($message->tool_calls)) {
            $toolCount = count($message->tool_calls);
            $lastTool = $message->tool_calls[$toolCount - 1]['name'] ?? 'Unknown';

            return "Used {$toolCount} tool(s). Last: {$lastTool}";
        }

        return $content ?: 'Task completed successfully.';
    }

    protected function handlePrematurePlanOnlyResult(Message $assistantMessage, array $toolCalls, ?array $resultMetadata): bool
    {
        if (! $this->shouldAutoRetryPrematurePlanOnlyResult($assistantMessage, $toolCalls, $resultMetadata)) {
            return false;
        }

        $metadata = $this->task->session_metadata ?? [];
        $metadata['premature_plan_retry_count'] = ($metadata['premature_plan_retry_count'] ?? 0) + 1;
        $metadata['last_premature_plan_response'] = Str::limit(trim((string) $assistantMessage->content), 500);

        $this->task->update([
            'status' => TaskStatus::Running,
            'completed_at' => null,
            'session_id' => (string) Str::uuid(),
            'session_metadata' => $metadata,
        ]);

        $retryMessage = Message::create([
            'task_id' => $this->task->id,
            'role' => MessageRole::User,
            'status' => MessageStatus::Sent,
            'content' => 'Continue from the previous attempt. The prior turn stopped after a short planning response. '
                .'Do not stop after planning. Use the available tools and complete the task, or report a concrete blocker.',
        ]);

        Log::warning('Auto-retrying premature plan-only Claude result', [
            'task_id' => $this->task->id,
            'assistant_message_id' => $assistantMessage->id,
            'retry_count' => $metadata['premature_plan_retry_count'],
        ]);

        self::dispatch($this->task->fresh(), $retryMessage, continue: false)->onQueue($this->queue ?? 'default');

        return true;
    }

    protected function shouldAutoRetryPrematurePlanOnlyResult(Message $assistantMessage, array $toolCalls, ?array $resultMetadata): bool
    {
        if ($this->task->isGeneralChat()) {
            return false;
        }

        if (! empty($toolCalls)) {
            return false;
        }

        if (($resultMetadata['subtype'] ?? null) !== 'success') {
            return false;
        }

        if (($resultMetadata['num_turns'] ?? null) !== 1) {
            return false;
        }

        if (($this->task->session_metadata['premature_plan_retry_count'] ?? 0) >= 1) {
            return false;
        }

        return $this->isAutonomousExecutionPrompt($this->userMessage->content ?? '')
            && $this->looksLikePlanningOnlyResponse($assistantMessage->content ?? '');
    }

    protected function isAutonomousExecutionPrompt(?string $content): bool
    {
        $content = trim((string) $content);
        if ($content === '') {
            return false;
        }

        return str_contains($content, '## Task:')
            || str_contains($content, 'Commit your changes')
            || mb_strlen($content) >= 500;
    }

    protected function looksLikePlanningOnlyResponse(?string $content): bool
    {
        $content = trim((string) $content);
        if ($content === '' || mb_strlen($content) > 280) {
            return false;
        }

        $normalized = Str::lower(preg_replace('/\s+/', ' ', $content) ?? $content);

        return str_starts_with($normalized, "i'll ")
            || str_starts_with($normalized, 'i will ')
            || str_starts_with($normalized, 'let me ')
            || str_contains($normalized, 'let me first ')
            || str_contains($normalized, "i'll start by")
            || str_contains($normalized, 'i will start by');
    }

    public function buildCommand(): string
    {
        $sessionId = escapeshellarg($this->task->session_id);

        // Check if we have images - use stream-json input format if so
        $hasImages = ! empty($this->userMessage->images);

        if ($hasImages) {
            // Use stream-json input mode for multimodal messages
            $claudeCmd = '/usr/bin/claude --print --input-format stream-json --output-format stream-json --verbose --dangerously-skip-permissions';
        } else {
            // Simple text-only mode
            $prompt = escapeshellarg($this->userMessage->content);
            $claudeCmd = "/usr/bin/claude -p {$prompt} --output-format stream-json --verbose --dangerously-skip-permissions";
        }

        // Add MCP servers (Playwright for browser automation)
        $mcpConfig = $this->getMcpConfig();
        if ($mcpConfig) {
            $claudeCmd .= ' --mcp-config '.escapeshellarg($mcpConfig);
        }

        if ($this->continue) {
            // Resume existing session
            $claudeCmd .= " --resume {$sessionId}";
        } else {
            // Start new session with specific ID
            $claudeCmd .= " --session-id {$sessionId}";
        }

        if ($this->task->max_turns) {
            $claudeCmd .= " --max-turns {$this->task->max_turns}";
        }

        // Build environment variable string for env command
        // We use env -i to clear ALL inherited environment variables,
        // ensuring the workspace's .env file is used for database credentials.
        $envVars = [
            'HOME' => getenv('HOME') ?: '/home/ploi',
            'PATH' => '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin',
            'USER' => 'ploi',
            'SHELL' => '/bin/bash',
            'TERM' => 'xterm-256color',
        ];

        // Add provider environment variables (API keys)
        foreach ($this->getProviderEnvironment() as $key => $value) {
            $envVars[$key] = $value;
        }

        // Build the env command with all variables
        $envCmd = 'env -i';
        foreach ($envVars as $key => $value) {
            $envCmd .= ' '.escapeshellarg("{$key}={$value}");
        }

        return "{$envCmd} {$claudeCmd}";
    }

    protected function getMcpConfig(): ?string
    {
        $mcpServers = [
            'playwright' => [
                'command' => 'npx',
                'args' => ['@playwright/mcp@latest'],
            ],
        ];

        return json_encode(['mcpServers' => $mcpServers]);
    }

    /**
     * Build a stream-json input message with images for multimodal messages.
     *
     * @return string JSON line to send via stdin
     */
    protected function buildStreamJsonInput(): string
    {
        $content = [];

        // Add images first
        if (! empty($this->userMessage->images)) {
            foreach ($this->userMessage->images as $image) {
                // Images are stored as {data: "data:image/png;base64,...", name: "filename"}
                $dataUrl = $image['data'] ?? '';

                // Parse data URL to extract media type and base64 data
                if (preg_match('/^data:(image\/\w+);base64,(.+)$/', $dataUrl, $matches)) {
                    $content[] = [
                        'type' => 'image',
                        'source' => [
                            'type' => 'base64',
                            'media_type' => $matches[1],
                            'data' => $matches[2],
                        ],
                    ];
                }
            }
        }

        // Add text content
        if (! empty($this->userMessage->content)) {
            $content[] = [
                'type' => 'text',
                'text' => $this->userMessage->content,
            ];
        }

        return json_encode([
            'type' => 'user',
            'message' => [
                'role' => 'user',
                'content' => $content,
            ],
        ]);
    }

    /**
     * Check if this message has images attached.
     */
    public function hasImages(): bool
    {
        return ! empty($this->userMessage->images);
    }

    /**
     * @return array<string, string>
     */
    public function getProviderEnvironment(): array
    {
        $provider = $this->task->aiProvider;

        if (! $provider) {
            return [];
        }

        return $provider->getEnvironmentVariables();
    }

    /**
     * Dispatch a compact command to reduce context usage.
     */
    protected function dispatchCompact(): void
    {
        // Don't auto-dispatch if the user message was already /compact (prevent infinite loop)
        if (trim($this->userMessage->content) === '/compact') {
            Log::warning('Compact failed - session needs reset', ['task_id' => $this->task->id]);

            return;
        }

        // Create a system message for the compact request
        $compactMessage = Message::create([
            'task_id' => $this->task->id,
            'role' => MessageRole::User,
            'status' => MessageStatus::Sent,
            'content' => '/compact',
        ]);

        // Dispatch a new job to send the compact command
        self::dispatch($this->task, $compactMessage, continue: true);
    }

    /**
     * Include any queued messages in the current run.
     * Called at the start of continued sessions to pick up messages that were
     * queued between jobs (race condition prevention).
     */
    protected function includeQueuedMessagesInCurrentRun(): void
    {
        $queuedMessages = $this->task->messages()
            ->where('status', MessageStatus::Queued)
            ->where('role', MessageRole::User)
            ->oldest()
            ->get();

        if ($queuedMessages->isEmpty()) {
            return;
        }

        Log::info('Including queued messages in current run', [
            'task_id' => $this->task->id,
            'count' => $queuedMessages->count(),
            'current_message' => substr($this->userMessage->content, 0, 50),
        ]);

        // Combine queued messages with the current message
        $combinedContent = [];
        $allImages = $this->userMessage->images ?? [];

        foreach ($queuedMessages as $message) {
            if (! empty($message->content)) {
                $combinedContent[] = $message->content;
            }

            if (! empty($message->images)) {
                $allImages = array_merge($allImages, $message->images);
            }

            // Mark the queued message as sent
            $message->markAsSent();
        }

        // Add the current message content
        if (! empty($this->userMessage->content)) {
            $combinedContent[] = $this->userMessage->content;
        }

        // Update the userMessage with combined content
        $this->userMessage = new Message([
            'task_id' => $this->task->id,
            'role' => MessageRole::User,
            'status' => MessageStatus::Sent,
            'content' => implode("\n\n", $combinedContent),
            'images' => ! empty($allImages) ? $allImages : null,
        ]);
        $this->userMessage->id = $queuedMessages->last()->id;
    }

    /**
     * Process any queued messages after Claude finishes responding.
     * Combines all queued messages into a single prompt sent to Claude,
     * while keeping them as separate visible messages in the chat history.
     */
    protected function processQueuedMessages(): void
    {
        $queuedMessages = $this->task->messages()
            ->where('status', MessageStatus::Queued)
            ->where('role', MessageRole::User)
            ->oldest()
            ->get();

        if ($queuedMessages->isEmpty()) {
            return;
        }

        Log::info('Processing queued messages', [
            'task_id' => $this->task->id,
            'count' => $queuedMessages->count(),
        ]);

        // Combine all queued messages into content parts for Claude
        $combinedContent = [];
        $allImages = [];

        foreach ($queuedMessages as $message) {
            if (! empty($message->content)) {
                $combinedContent[] = $message->content;
            }

            if (! empty($message->images)) {
                $allImages = array_merge($allImages, $message->images);
            }

            // Mark the queued message as sent so it appears in chat history
            $message->markAsSent();
        }

        if (empty($combinedContent) && empty($allImages)) {
            return;
        }

        // Use the last queued message as the "trigger" message for the job
        // but update its content to be the combined content for Claude
        $lastMessage = $queuedMessages->last();

        // Create a synthetic message object for the job with combined content
        // We don't save this to the DB - it's just for the Claude API call
        $syntheticMessage = new Message([
            'task_id' => $this->task->id,
            'role' => MessageRole::User,
            'status' => MessageStatus::Sent,
            'content' => implode("\n\n", $combinedContent),
            'images' => ! empty($allImages) ? $allImages : null,
        ]);
        // Set the ID so hasImages() and other methods work
        $syntheticMessage->id = $lastMessage->id;

        // Dispatch a new job to process the combined content (preserve queue)
        self::dispatch($this->task, $syntheticMessage, continue: true)->onQueue($this->queue ?? 'default');
    }

    /**
     * Handle a job failure (timeout, exception, etc.)
     */
    public function failed(\Throwable $exception): void
    {
        // Rate limit exceptions should not mark the task as failed if there are retries remaining
        // The task status was already set to pending in the handle() method
        if ($exception instanceof RateLimitException) {
            Log::warning('RunClaudeMessageJob rate limit exhausted all retries', [
                'task_id' => $this->task->id,
                'reset_time' => $exception->getResetDescription(),
            ]);

            // Only mark as failed after all retries are exhausted
            $this->task->markAsFailed();

            $this->sendPushNotification(
                'Task Rate Limited',
                "Rate limit exceeded. Resets: {$exception->getResetDescription()}",
                false
            );

            return;
        }

        Log::error('RunClaudeMessageJob failed', [
            'task_id' => $this->task->id,
            'exception' => $exception->getMessage(),
        ]);

        $this->task->markAsFailed();

        $this->sendPushNotification(
            'Task Failed',
            "Error: {$exception->getMessage()}",
            false
        );
    }

    /**
     * @return array{content?: string, tool_call?: array<string, mixed>, usage?: array<string, mixed>}|null
     */
    private function parseLine(string $line): ?array
    {
        $line = trim($line);
        if (empty($line)) {
            return null;
        }

        $data = json_decode($line, true);
        if (! $data) {
            return null;
        }

        $result = [];

        if (($data['type'] ?? '') === 'assistant') {
            // Check for rate limit error FIRST (before content parsing)
            // Rate limit errors appear as: {"type":"assistant","error":"rate_limit","isApiErrorMessage":true,...}
            if (($data['error'] ?? '') === 'rate_limit' || ($data['isApiErrorMessage'] ?? false)) {
                $rateLimitMessage = '';
                $resetTime = null;

                // Extract the message text if present
                if (isset($data['message']['content'])) {
                    foreach ($data['message']['content'] as $block) {
                        if (($block['type'] ?? '') === 'text') {
                            $rateLimitMessage = $block['text'] ?? '';
                            // Try to extract reset time from message like "resets 6pm (UTC)"
                            if (preg_match('/resets?\s+(\d+[ap]m\s*\([^)]+\))/i', $rateLimitMessage, $matches)) {
                                $resetTime = trim($matches[1]);
                            }
                            break;
                        }
                    }
                }

                $result['rate_limit'] = [
                    'message' => $rateLimitMessage ?: 'Rate limit exceeded',
                    'reset_time' => $resetTime,
                    'error_type' => $data['error'] ?? 'rate_limit',
                ];

                return $result;
            }

            if (isset($data['message']['content'])) {
                foreach ($data['message']['content'] as $block) {
                    if (($block['type'] ?? '') === 'text') {
                        $result['content'] = $block['text'] ?? '';
                    }
                    if (($block['type'] ?? '') === 'tool_use') {
                        $result['tool_call'] = [
                            'id' => $block['id'] ?? null,
                            'name' => $block['name'] ?? '',
                            'input' => $block['input'] ?? [],
                        ];
                    }
                }
            }

            // Track per-turn context usage from assistant events
            // This gives us the actual context window usage for this turn (not cumulative)
            if (isset($data['message']['usage'])) {
                $usage = $data['message']['usage'];
                $inputTokens = ($usage['input_tokens'] ?? 0)
                    + ($usage['cache_read_input_tokens'] ?? 0)
                    + ($usage['cache_creation_input_tokens'] ?? 0);
                $outputTokens = $usage['output_tokens'] ?? 0;

                // Only track if we have actual token counts (skip zero/empty usage blocks)
                if ($inputTokens > 0 || $outputTokens > 0) {
                    $result['turn_usage'] = [
                        'input_tokens' => $inputTokens,
                        'output_tokens' => $outputTokens,
                    ];
                }
            }
        }

        if (($data['type'] ?? '') === 'result') {
            // Signal that result was received, cost comes from here
            $result['usage'] = [
                'cost_usd' => $data['total_cost_usd'] ?? null,
            ];

            // Capture the final result summary text if present
            if (! empty($data['result'])) {
                $result['result_summary'] = $data['result'];
            }

            // Capture session result metadata (model usage breakdown, duration, turns)
            $result['result_metadata'] = [
                'duration_ms' => $data['duration_ms'] ?? null,
                'duration_api_ms' => $data['duration_api_ms'] ?? null,
                'num_turns' => $data['num_turns'] ?? null,
                'model_usage' => $data['modelUsage'] ?? null,
                'is_error' => $data['is_error'] ?? false,
                'subtype' => $data['subtype'] ?? null,
            ];
        }

        // Extract session init metadata (model, MCP servers, skills, etc.)
        if (($data['type'] ?? '') === 'system' && ($data['subtype'] ?? '') === 'init') {
            $result['init_metadata'] = [
                'model' => $data['model'] ?? null,
                'claude_code_version' => $data['claude_code_version'] ?? null,
                'mcp_servers' => $data['mcp_servers'] ?? [],
                'skills' => $data['skills'] ?? [],
                'tools' => $data['tools'] ?? [],
                'agents' => $data['agents'] ?? [],
                'plugins' => $data['plugins'] ?? [],
            ];
        }

        // Detect context compaction events
        if (($data['type'] ?? '') === 'system' && ($data['subtype'] ?? '') === 'compact_boundary') {
            $result['compacting'] = [
                'trigger' => $data['compact_metadata']['trigger'] ?? 'auto',
                'pre_tokens' => $data['compact_metadata']['pre_tokens'] ?? null,
            ];
        }

        if (($data['type'] ?? '') === 'system' && ($data['subtype'] ?? '') === 'task_started') {
            $result['subagent_started'] = [
                'task_id' => $data['task_id'] ?? null,
                'description' => $data['description'] ?? null,
                'task_type' => $data['task_type'] ?? null,
            ];
        }

        // Detect context low warning (requires manual /compact)
        if (($data['type'] ?? '') === 'system' && str_contains($data['message'] ?? '', 'Context low')) {
            $result['context_low'] = true;
        }

        // Detect "Prompt is too long" error - context exceeded hard limit
        if (($data['type'] ?? '') === 'assistant') {
            $content = $data['message']['content'] ?? [];
            foreach ($content as $block) {
                if (($block['type'] ?? '') === 'text' && str_contains($block['text'] ?? '', 'Prompt is too long')) {
                    $result['prompt_too_long'] = true;
                }
            }
        }

        return $result ?: null;
    }
}
