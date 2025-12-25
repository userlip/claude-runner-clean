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

    public int $timeout = 600;

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

            $process = proc_open($command, $descriptors, $pipes, $workingDir);

            if (! is_resource($process)) {
                throw new \RuntimeException('Failed to start Claude process');
            }

            fclose($pipes[0]);

            $output = '';
            $toolCalls = [];

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
                    if (isset($parsed['usage'])) {
                        $assistantMessage->update([
                            'tokens_in' => $parsed['usage']['input_tokens'] ?? null,
                            'tokens_out' => $parsed['usage']['output_tokens'] ?? null,
                            'cost_usd' => $parsed['usage']['cost_usd'] ?? null,
                        ]);
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

        $cmd = "claude -p {$prompt} --output-format stream-json --verbose --dangerously-skip-permissions";

        if ($this->continue) {
            // Resume existing session
            $cmd .= " --resume {$sessionId}";
        } else {
            // Start new session with specific ID
            $cmd .= " --session-id {$sessionId}";
        }

        return $cmd;
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
            $result['usage'] = [
                'input_tokens' => $data['total_input_tokens'] ?? null,
                'output_tokens' => $data['total_output_tokens'] ?? null,
                'cost_usd' => $data['total_cost_usd'] ?? null,
            ];
        }

        return $result ?: null;
    }
}
