<?php

namespace App\Jobs;

use App\Enums\MessageRole;
use App\Enums\MessageStatus;
use App\Models\Message;
use App\Models\Task;
use App\Services\TaskPullRequestDetectionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class RunCodexMessageJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 10800;

    public int $tries = 1;

    public function __construct(
        public Task $task,
        public Message $userMessage,
        public bool $continue = false
    ) {}

    public function handle(): void
    {
        $this->task->markAsRunning();

        if ($this->continue) {
            $this->includeQueuedMessagesInCurrentRun();
        }

        $assistantMessage = Message::create([
            'task_id' => $this->task->id,
            'role' => MessageRole::Assistant,
            'status' => MessageStatus::Sent,
            'content' => '',
        ]);

        $tempImages = [];

        try {
            $this->ensureCodexInitMetadata();
            $command = $this->buildCommand($tempImages);
            $workingDir = $this->task->working_directory;

            if (! is_dir($workingDir)) {
                throw new \RuntimeException("Working directory does not exist: {$workingDir}. The repository may still be cloning.");
            }

            Log::info('Running Codex CLI', [
                'task_id' => $this->task->id,
                'command' => $command,
                'working_dir' => $workingDir,
            ]);

            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];

            $process = proc_open($command, $descriptors, $pipes, $workingDir, null);

            if (! is_resource($process)) {
                throw new \RuntimeException('Failed to start Codex process');
            }

            $prompt = $this->userMessage->content ?? '';
            fwrite($pipes[0], $prompt."\n");
            fclose($pipes[0]);

            $toolCalls = [];
            $contentBlocks = [];
            $lastTurnUsage = null;
            $resultReceived = false;

            while (! feof($pipes[1])) {
                $line = fgets($pipes[1]);
                if ($line === false) {
                    continue;
                }

                $assistantMessage->appendRawOutput($line);

                $parsed = $this->parseLine($line);
                if (! $parsed) {
                    continue;
                }

                if (isset($parsed['thread_id'])) {
                    $this->updateSessionMetadata($parsed['thread_id']);
                }

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
                }

                if (isset($parsed['content'])) {
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
                    $lastTurnUsage = $parsed['turn_usage'];
                }

                if (isset($parsed['result'])) {
                    if ($lastTurnUsage) {
                        $assistantMessage->update([
                            'tokens_in' => $lastTurnUsage['input_tokens'] ?? null,
                            'tokens_out' => $lastTurnUsage['output_tokens'] ?? null,
                        ]);

                        if ($this->task->aiProvider) {
                            $this->task->aiProvider->incrementUsage(
                                $lastTurnUsage['input_tokens'] ?? 0,
                                $lastTurnUsage['output_tokens'] ?? 0
                            );
                        }
                    }

                    $resultReceived = true;
                    break;
                }
            }

            fclose($pipes[1]);
            fclose($pipes[2]);

            proc_terminate($process);
            proc_close($process);

            if ($resultReceived) {
                $this->task->markAsCompleted();
                Log::debug('Task marked as completed (Codex turn completed)', ['task_id' => $this->task->id]);

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

                $this->processQueuedMessages();
            } else {
                Log::warning('Codex process ended without turn completion', [
                    'task_id' => $this->task->id,
                ]);
                $this->task->markAsCompleted();
            }
        } catch (\Throwable $e) {
            Log::error("Codex execution failed: {$e->getMessage()}");

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

            $this->sendPushNotification(
                'Task Failed',
                "Error: {$e->getMessage()}",
                false
            );

            throw $e;
        } finally {
            $this->cleanupTempImages($tempImages);
        }
    }

    protected function buildCommand(array &$tempImages): string
    {
        $sessionId = $this->task->session_id;
        $workingDir = $this->task->working_directory;
        $codexPath = config('services.codex.path', '/home/ploi/.npm-global/bin/codex');

        $imageArgs = $this->buildImageArgs($tempImages);
        $model = $this->task->aiProvider?->model;

        if ($this->continue && $sessionId) {
            $baseArgs = [
                escapeshellcmd($codexPath),
                'exec',
                '--cd',
                escapeshellarg($workingDir),
                'resume',
                '--json',
                '--skip-git-repo-check',
                '--dangerously-bypass-approvals-and-sandbox',
            ];

            if ($model) {
                $baseArgs[] = '-m';
                $baseArgs[] = escapeshellarg($model);
            }

            $baseArgs[] = escapeshellarg($sessionId);
            $baseArgs[] = '-';
        } else {
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
        }

        $codexCmd = implode(' ', $baseArgs).$imageArgs;

        $envVars = [
            'HOME' => getenv('HOME') ?: '/home/ploi',
            'PATH' => '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin',
            'USER' => 'ploi',
            'SHELL' => '/bin/bash',
            'TERM' => 'xterm-256color',
        ];

        $envCmd = 'env -i';
        foreach ($envVars as $key => $value) {
            $envCmd .= ' '.escapeshellarg("{$key}={$value}");
        }

        return "{$envCmd} {$codexCmd}";
    }

    protected function buildImageArgs(array &$tempImages): string
    {
        if (empty($this->userMessage->images)) {
            return '';
        }

        $args = '';
        foreach ($this->userMessage->images as $image) {
            $dataUrl = $image['data'] ?? '';
            $filePath = $this->writeTempImage($dataUrl);
            if ($filePath) {
                $tempImages[] = $filePath;
                $args .= ' -i '.escapeshellarg($filePath);
            }
        }

        return $args;
    }

    protected function writeTempImage(string $dataUrl): ?string
    {
        if (! preg_match('/^data:(image\/[a-zA-Z0-9.+-]+);base64,(.+)$/', $dataUrl, $matches)) {
            return null;
        }

        $data = base64_decode($matches[2], true);
        if ($data === false) {
            return null;
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'codex-img-');
        if (! $tempPath) {
            return null;
        }

        file_put_contents($tempPath, $data);

        return $tempPath;
    }

    protected function cleanupTempImages(array $tempImages): void
    {
        foreach ($tempImages as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    protected function updateSessionMetadata(?string $threadId): void
    {
        if (! $threadId) {
            return;
        }

        $metadata = $this->task->session_metadata ?? [];
        $metadata['init'] = array_merge($metadata['init'] ?? [], [
            'model' => $this->task->aiProvider?->model,
            'codex_thread_id' => $threadId,
        ]);

        $this->task->update([
            'session_metadata' => $metadata,
            'session_id' => $threadId,
        ]);
    }

    protected function ensureCodexInitMetadata(): void
    {
        $metadata = $this->task->session_metadata ?? [];
        $init = $metadata['init'] ?? [];

        if (! empty($init['mcp_servers'])) {
            return;
        }

        if ($this->continue && ! empty($metadata)) {
            return;
        }

        $mcpServers = $this->fetchMcpServers();
        if (empty($mcpServers)) {
            return;
        }

        $init['mcp_servers'] = $mcpServers;
        $init['model'] ??= $this->task->aiProvider?->model;
        $metadata['init'] = $init;

        $this->task->update([
            'session_metadata' => $metadata,
        ]);
    }

    /**
     * @return array<int, array{name: string, status: string, auth?: string}>
     */
    protected function fetchMcpServers(): array
    {
        $codexPath = config('services.codex.path', '/home/ploi/.npm-global/bin/codex');

        $result = Process::timeout(10)->run([$codexPath, 'mcp', 'list']);
        if (! $result->successful()) {
            Log::warning('Codex MCP list failed', [
                'task_id' => $this->task->id,
                'error' => $result->errorOutput(),
            ]);

            return [];
        }

        return $this->parseMcpListOutput($result->output());
    }

    /**
     * @return array<int, array{name: string, status: string, auth?: string}>
     */
    protected function parseMcpListOutput(string $output): array
    {
        $lines = preg_split("/\r\n|\r|\n/", trim($output));
        if (empty($lines)) {
            return [];
        }

        $servers = [];
        $header = null;
        $nameIndex = null;
        $statusIndex = null;
        $authIndex = null;
        $sectionType = null;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                $header = null;
                $nameIndex = null;
                $statusIndex = null;
                $authIndex = null;

                continue;
            }

            $parts = preg_split('/\s{2,}/', $line);
            if (! $parts || count($parts) === 0) {
                continue;
            }

            if (str_starts_with($line, 'Name')) {
                $header = $parts;
                $nameIndex = array_search('Name', $header, true);
                $statusIndex = array_search('Status', $header, true);
                $authIndex = array_search('Auth', $header, true);
                $sectionType = in_array('Url', $header, true) ? 'remote' : 'local';

                continue;
            }

            if ($header === null || $nameIndex === false || $statusIndex === false) {
                continue;
            }

            $name = $parts[$nameIndex] ?? null;
            $status = $parts[$statusIndex] ?? null;
            $auth = $authIndex !== false ? ($parts[$authIndex] ?? null) : null;

            if (! $name || ! $status) {
                continue;
            }

            $row = [
                'name' => $name,
                'status' => $status,
            ];

            if ($sectionType) {
                $row['type'] = $sectionType;
            }

            if ($auth) {
                $row['auth'] = $auth;
            }

            $servers[] = $row;
        }

        return $servers;
    }

    /**
     * @return array{content?: string, tool_call?: array<string, mixed>, turn_usage?: array<string, int>, thread_id?: string, result?: bool}|null
     */
    protected function parseLine(string $line): ?array
    {
        $line = trim($line);
        if ($line === '') {
            return null;
        }

        $data = json_decode($line, true);
        if (! is_array($data)) {
            return null;
        }

        $result = [];

        if (($data['type'] ?? '') === 'thread.started') {
            $result['thread_id'] = $data['thread_id'] ?? null;
        }

        if (($data['type'] ?? '') === 'item.completed') {
            $item = $data['item'] ?? [];
            $itemType = $item['type'] ?? '';

            if (in_array($itemType, ['agent_message', 'assistant_message'], true)) {
                $result['content'] = $this->stripCitationMarkers($item['text'] ?? '');
            }

            if ($itemType === 'command_execution') {
                $result['tool_call'] = [
                    'id' => $item['id'] ?? null,
                    'name' => 'shell_command',
                    'input' => [
                        'command' => $item['command'] ?? null,
                        'exit_code' => $item['exit_code'] ?? null,
                        'output' => $item['aggregated_output'] ?? null,
                    ],
                ];
            }
        }

        if (($data['type'] ?? '') === 'turn.completed') {
            $usage = $data['usage'] ?? [];
            $inputTokens = ($usage['input_tokens'] ?? 0) + ($usage['cached_input_tokens'] ?? 0);
            $outputTokens = $usage['output_tokens'] ?? 0;

            $result['turn_usage'] = [
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
            ];
            $result['result'] = true;
        }

        return $result;
    }

    protected function sendPushNotification(string $title, string $body, bool $success): void
    {
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

        $combinedContent = [];
        $allImages = $this->userMessage->images ?? [];

        foreach ($queuedMessages as $message) {
            if (! empty($message->content)) {
                $combinedContent[] = $message->content;
            }

            if (! empty($message->images)) {
                $allImages = array_merge($allImages, $message->images);
            }

            $message->markAsSent();
        }

        if (! empty($this->userMessage->content)) {
            $combinedContent[] = $this->userMessage->content;
        }

        $this->userMessage = new Message([
            'task_id' => $this->task->id,
            'role' => MessageRole::User,
            'status' => MessageStatus::Sent,
            'content' => implode("\n\n", $combinedContent),
            'images' => ! empty($allImages) ? $allImages : null,
        ]);
        $this->userMessage->id = $queuedMessages->last()->id;
    }

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

        $combinedContent = [];
        $allImages = [];

        foreach ($queuedMessages as $message) {
            if (! empty($message->content)) {
                $combinedContent[] = $message->content;
            }

            if (! empty($message->images)) {
                $allImages = array_merge($allImages, $message->images);
            }

            $message->markAsSent();
        }

        if (empty($combinedContent) && empty($allImages)) {
            return;
        }

        $lastMessage = $queuedMessages->last();

        $syntheticMessage = new Message([
            'task_id' => $this->task->id,
            'role' => MessageRole::User,
            'status' => MessageStatus::Sent,
            'content' => implode("\n\n", $combinedContent),
            'images' => ! empty($allImages) ? $allImages : null,
        ]);
        $syntheticMessage->id = $lastMessage->id;

        self::dispatch($this->task, $syntheticMessage, continue: true);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('RunCodexMessageJob failed', [
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
     * Strip OpenAI citation markers from content.
     *
     * OpenAI models with web browsing produce markers like "citeturn0search0" or "citeturn6open0"
     * wrapped in Unicode private-use area characters (U+E200-U+E2FF).
     */
    protected function stripCitationMarkers(string $content): string
    {
        // Remove Unicode private-use area characters that wrap citations
        $content = preg_replace('/[\x{E200}-\x{E2FF}]+/u', '', $content);

        // Remove plain citeturn markers
        return preg_replace('/\s*citeturn\d+\w*\d*/i', '', $content);
    }
}
