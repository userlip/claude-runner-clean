<?php

namespace App\Jobs;

use App\Enums\MessageRole;
use App\Models\Message;
use App\Models\Task;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RunClaudeMessageJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 10800; // 3 hours for complex tasks

    public int $tries = 1;

    public function __construct(
        public Task $task,
        public Message $userMessage,
        public bool $continue = false
    ) {}

    public function handle(): void
    {
        $this->task->markAsRunning();

        $assistantMessage = Message::create([
            'task_id' => $this->task->id,
            'role' => MessageRole::Assistant,
            'content' => '',
        ]);

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

            fclose($pipes[0]);

            $output = '';
            $toolCalls = [];
            $contentBlocks = [];

            $resultReceived = false;
            while (! feof($pipes[1])) {
                $line = fgets($pipes[1]);
                if ($line === false) {
                    continue;
                }

                $output .= $line;
                $assistantMessage->appendRawOutput($line);

                $parsed = $this->parseLine($line);
                if ($parsed) {
                    if (isset($parsed['tool_call'])) {
                        $toolCalls[] = $parsed['tool_call'];
                        $contentBlocks[] = ['type' => 'tool_use', 'tool' => $parsed['tool_call']];
                        $assistantMessage->update([
                            'tool_calls' => $toolCalls,
                            'content_blocks' => $contentBlocks,
                        ]);
                    }
                    if (isset($parsed['content'])) {
                        $contentBlocks[] = ['type' => 'text', 'text' => $parsed['content']];
                        $assistantMessage->update([
                            'content' => ($assistantMessage->content ?? '').$parsed['content'],
                            'content_blocks' => $contentBlocks,
                        ]);
                    }
                    if (isset($parsed['compacting'])) {
                        Log::info('Context compaction started', [
                            'task_id' => $this->task->id,
                            'trigger' => $parsed['compacting']['trigger'],
                            'pre_tokens' => $parsed['compacting']['pre_tokens'],
                        ]);
                        $this->task->update(['is_compacting' => true]);
                        $this->task->increment('compaction_count');
                    }
                    if (isset($parsed['usage'])) {
                        Log::info('Result event received - breaking loop', [
                            'task_id' => $this->task->id,
                            'usage' => $parsed['usage'],
                        ]);

                        $assistantMessage->update([
                            'tokens_in' => $parsed['usage']['input_tokens'] ?? null,
                            'tokens_out' => $parsed['usage']['output_tokens'] ?? null,
                            'cost_usd' => $parsed['usage']['cost_usd'] ?? null,
                        ]);

                        // Track provider usage
                        if ($this->task->aiProvider) {
                            $this->task->aiProvider->incrementUsage(
                                $parsed['usage']['input_tokens'] ?? 0,
                                $parsed['usage']['output_tokens'] ?? 0
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
            fclose($pipes[1]);
            fclose($pipes[2]);

            if ($resultReceived) {
                // Claude finished responding - mark completed and notify
                $this->task->markAsCompleted();
                Log::debug('Task marked as completed (result received)', ['task_id' => $this->task->id]);

                $this->sendPushNotification(
                    'Task Completed',
                    $this->getNotificationBody($assistantMessage),
                    true
                );

                // Kill the subprocess since we're done with it
                // The main Claude session keeps running for future messages
                proc_terminate($process);
                proc_close($process);
            } else {
                // No result received - process may have exited unexpectedly
                $exitCode = proc_close($process);
                Log::warning('Claude process ended without result event', [
                    'exit_code' => $exitCode,
                    'task_id' => $this->task->id,
                ]);
                $this->task->markAsCompleted();
            }

        } catch (\Throwable $e) {
            Log::error("Claude execution failed: {$e->getMessage()}");

            $assistantMessage->update([
                'content' => "Error: {$e->getMessage()}",
            ]);

            $this->task->markAsFailed();

            // Send push notification on failure
            $this->sendPushNotification(
                'Task Failed',
                "Error: {$e->getMessage()}",
                false
            );

            throw $e;
        }
    }

    protected function sendPushNotification(string $title, string $body, bool $success): void
    {
        // Get user through repository since tasks don't have user_id directly
        $user = $this->task->repository?->user;

        Log::debug('sendPushNotification called', [
            'user_id' => $user?->id,
            'push_enabled' => $user?->push_notifications_enabled,
            'title' => $title,
        ]);

        if (! $user || ! $user->push_notifications_enabled) {
            Log::debug('Push notification skipped - user not found or push disabled');

            return;
        }

        $taskTitle = $this->task->title ?? 'Untitled Task';
        $fullTitle = $success ? "Completed: {$taskTitle}" : "Failed: {$taskTitle}";

        SendPushNotificationJob::dispatch(
            $user,
            $fullTitle,
            Str::limit($body, 150),
            route('filament.admin.resources.tasks.chat', ['record' => $this->task->uuid]),
            [
                ['action' => 'view', 'title' => 'View Task'],
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

    public function buildCommand(): string
    {
        $prompt = escapeshellarg($this->userMessage->content);
        $sessionId = escapeshellarg($this->task->session_id);

        $claudeCmd = "/usr/bin/claude -p {$prompt} --output-format stream-json --verbose --dangerously-skip-permissions";

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
     * Handle a job failure (timeout, exception, etc.)
     */
    public function failed(\Throwable $exception): void
    {
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

        if (($data['type'] ?? '') === 'assistant' && isset($data['message']['content'])) {
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

        if (($data['type'] ?? '') === 'result') {
            // input_tokens represents total context window usage for this request
            // cache_read_input_tokens and cache_creation_input_tokens are billing breakdowns,
            // not additional tokens - they're subsets of input_tokens
            $usage = $data['usage'] ?? [];
            $inputTokens = $usage['input_tokens'] ?? 0;
            $outputTokens = $usage['output_tokens'] ?? 0;

            $result['usage'] = [
                'input_tokens' => $inputTokens > 0 ? $inputTokens : null,
                'output_tokens' => $outputTokens > 0 ? $outputTokens : null,
                'cost_usd' => $data['total_cost_usd'] ?? null,
            ];
        }

        // Detect context compaction events
        if (($data['type'] ?? '') === 'system' && ($data['subtype'] ?? '') === 'compact_boundary') {
            $result['compacting'] = [
                'trigger' => $data['compact_metadata']['trigger'] ?? 'auto',
                'pre_tokens' => $data['compact_metadata']['pre_tokens'] ?? null,
            ];
        }

        return $result ?: null;
    }
}
