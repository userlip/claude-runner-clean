<?php

namespace App\Jobs;

use App\Enums\MessageRole;
use App\Models\GeneralChat;
use App\Models\GeneralChatMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class RunGeneralChatMessageJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 10800; // 3 hours for complex tasks

    public int $tries = 1;

    public function __construct(
        public GeneralChat $chat,
        public GeneralChatMessage $userMessage,
        public bool $continue = false
    ) {}

    public function handle(): void
    {
        $this->chat->markAsRunning();

        $assistantMessage = GeneralChatMessage::create([
            'general_chat_id' => $this->chat->id,
            'role' => MessageRole::Assistant,
            'content' => '',
        ]);

        try {
            $command = $this->buildCommand();
            $workingDir = $this->chat->working_directory;

            if (! is_dir($workingDir)) {
                throw new \RuntimeException("Working directory does not exist: {$workingDir}");
            }

            Log::info('Running Claude Code (General Chat)', [
                'chat_id' => $this->chat->id,
                'command' => $command,
                'working_dir' => $workingDir,
            ]);

            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];

            $env = array_filter(
                array_merge($_ENV, $_SERVER, $this->getProviderEnvironment(), [
                    'PATH' => '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin',
                    'HOME' => getenv('HOME') ?: '/home/ploi',
                ]),
                fn ($value) => is_string($value)
            );
            $process = proc_open($command, $descriptors, $pipes, $workingDir, $env);

            if (! is_resource($process)) {
                throw new \RuntimeException('Failed to start Claude process');
            }

            fclose($pipes[0]);

            $output = '';
            $toolCalls = [];
            $lastTurnUsage = null; // Track the last turn's context usage

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
                        $assistantMessage->update(['tool_calls' => $toolCalls]);
                    }
                    if (isset($parsed['content'])) {
                        $assistantMessage->update([
                            'content' => ($assistantMessage->content ?? '').$parsed['content'],
                        ]);
                    }
                    if (isset($parsed['turn_usage'])) {
                        // Track the latest turn's context usage (overwrites previous)
                        $lastTurnUsage = $parsed['turn_usage'];
                    }
                    if (isset($parsed['compacting'])) {
                        Log::info('Context compaction started', [
                            'chat_id' => $this->chat->id,
                            'trigger' => $parsed['compacting']['trigger'],
                            'pre_tokens' => $parsed['compacting']['pre_tokens'],
                        ]);
                        $this->chat->update(['is_compacting' => true]);
                        $this->chat->increment('compaction_count');
                    }
                    if (isset($parsed['usage'])) {
                        // Use last turn's context usage (actual context window usage)
                        $assistantMessage->update([
                            'tokens_in' => $lastTurnUsage['input_tokens'] ?? null,
                            'tokens_out' => $lastTurnUsage['output_tokens'] ?? null,
                            'cost_usd' => $parsed['usage']['cost_usd'] ?? null,
                        ]);

                        // Track provider usage with last turn's tokens
                        if ($this->chat->aiProvider && $lastTurnUsage) {
                            $this->chat->aiProvider->incrementUsage(
                                $lastTurnUsage['input_tokens'] ?? 0,
                                $lastTurnUsage['output_tokens'] ?? 0
                            );
                        }

                        // Clear compacting state when usage/result is received
                        $this->chat->update(['is_compacting' => false]);
                    }
                }
            }

            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);

            $exitCode = proc_close($process);

            if ($exitCode !== 0) {
                Log::warning("Claude exited with code {$exitCode}", ['stderr' => $stderr]);
            }

            $this->chat->markAsCompleted();

        } catch (\Throwable $e) {
            Log::error("Claude execution failed: {$e->getMessage()}");

            $assistantMessage->update([
                'content' => "Error: {$e->getMessage()}",
            ]);

            $this->chat->markAsFailed();

            throw $e;
        }
    }

    public function buildCommand(): string
    {
        $prompt = escapeshellarg($this->userMessage->content);
        $sessionId = escapeshellarg($this->chat->session_id);

        $cmd = "/usr/bin/claude -p {$prompt} --output-format stream-json --verbose --dangerously-skip-permissions";

        // Add MCP servers (Playwright for browser automation)
        $mcpConfig = $this->getMcpConfig();
        if ($mcpConfig) {
            $cmd .= ' --mcp-config '.escapeshellarg($mcpConfig);
        }

        if ($this->continue) {
            // Resume existing session
            $cmd .= " --resume {$sessionId}";
        } else {
            // Start new session with specific ID
            $cmd .= " --session-id {$sessionId}";
        }

        return $cmd;
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
        $provider = $this->chat->aiProvider;

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
        Log::error('RunGeneralChatMessageJob failed', [
            'chat_id' => $this->chat->id,
            'exception' => $exception->getMessage(),
        ]);

        $this->chat->markAsFailed();
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
            if (isset($data['message']['usage'])) {
                $usage = $data['message']['usage'];
                $inputTokens = ($usage['input_tokens'] ?? 0)
                    + ($usage['cache_read_input_tokens'] ?? 0)
                    + ($usage['cache_creation_input_tokens'] ?? 0);
                $outputTokens = $usage['output_tokens'] ?? 0;

                $result['turn_usage'] = [
                    'input_tokens' => $inputTokens,
                    'output_tokens' => $outputTokens,
                ];
            }
        }

        if (($data['type'] ?? '') === 'result') {
            // Signal that result was received, cost comes from here
            $result['usage'] = [
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
