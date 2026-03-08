<?php

namespace App\Jobs;

use App\Enums\MessageRole;
use App\Enums\TaskStatus;
use App\Models\Message;
use App\Models\Proposal;
use App\Models\Task;
use App\Services\PersonaStorageService;
use App\Services\TelegramService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GeneratePersonaSubtasksJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 10800; // 3 hours

    public int $tries = 1;

    public function __construct(
        public Proposal $proposal,
        public Task $task
    ) {}

    public function handle(): void
    {
        $this->proposal->refresh();
        $this->task->refresh();

        $persona = $this->proposal->persona;

        Log::info('Generating persona subtasks', [
            'proposal_id' => $this->proposal->id,
            'persona' => $persona->name,
            'task_id' => $this->task->id,
        ]);

        $this->task->markAsRunning();

        $prompt = $this->buildPrompt();

        $message = $this->task->messages()->create([
            'role' => MessageRole::User,
            'content' => $prompt,
        ]);

        $assistantMessage = Message::create([
            'task_id' => $this->task->id,
            'role' => MessageRole::Assistant,
            'content' => '',
        ]);

        try {
            $command = $this->buildCommand($prompt);
            $workingDir = $this->task->working_directory;

            if (! is_dir($workingDir)) {
                throw new \RuntimeException("Working directory does not exist: {$workingDir}");
            }

            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];

            $process = proc_open($command, $descriptors, $pipes, $workingDir, null);

            if (! is_resource($process)) {
                throw new \RuntimeException('Failed to start Claude process');
            }

            fclose($pipes[0]);

            $streamTimeoutSeconds = 300;
            stream_set_timeout($pipes[1], $streamTimeoutSeconds);

            $output = '';
            $resultText = '';
            $resultReceived = false;

            while (! feof($pipes[1])) {
                $line = fgets($pipes[1]);
                if ($line === false) {
                    $meta = stream_get_meta_data($pipes[1]);
                    if ($meta['timed_out']) {
                        Log::error('Claude stdout stream timed out for subtask generation', [
                            'task_id' => $this->task->id,
                        ]);

                        break;
                    }

                    continue;
                }

                $output .= $line;
                $parsed = $this->parseLine($line);

                if ($parsed) {
                    if (isset($parsed['content'])) {
                        $resultText .= $parsed['content'];
                        $assistantMessage->update([
                            'content' => ($assistantMessage->content ?? '').$parsed['content'],
                        ]);
                    }

                    if (isset($parsed['result'])) {
                        if (isset($parsed['result_summary'])) {
                            $resultText .= "\n\n".$parsed['result_summary'];
                            $currentContent = $assistantMessage->content ?? '';
                            $resultSummary = $parsed['result_summary'];
                            if (! str_ends_with(trim($currentContent), trim($resultSummary))) {
                                $assistantMessage->update([
                                    'content' => $currentContent."\n\n".$resultSummary,
                                ]);
                            }
                        }

                        $assistantMessage->update([
                            'cost_usd' => $parsed['cost_usd'] ?? null,
                        ]);

                        $resultReceived = true;

                        break;
                    }
                }
            }

            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_terminate($process);
            proc_close($process);

            if ($resultReceived) {
                $this->handleSuccess($resultText);
            } else {
                $this->handleFailure('Claude process ended without result event');
            }
        } catch (\Throwable $e) {
            Log::error('Persona subtask generation failed', [
                'proposal_id' => $this->proposal->id,
                'error' => $e->getMessage(),
            ]);

            $assistantMessage->update([
                'content' => "Error: {$e->getMessage()}",
            ]);

            $this->handleFailure($e->getMessage());

            throw $e;
        }
    }

    protected function handleSuccess(string $resultText): void
    {
        $subtasks = $this->parseSubtasks($resultText);

        if (empty($subtasks)) {
            $this->handleFailure('Failed to parse subtasks from Claude response');

            return;
        }

        $this->proposal->update([
            'subtasks' => $subtasks,
            'current_subtask_index' => 0,
        ]);

        $this->task->update([
            'status' => TaskStatus::Completed,
            'completed_at' => now(),
        ]);

        Log::info('Persona subtasks generated', [
            'proposal_id' => $this->proposal->id,
            'subtask_count' => count($subtasks),
        ]);

        $this->sendSubtaskApprovalNotification($subtasks);
    }

    protected function handleFailure(string $reason): void
    {
        $this->task->update([
            'status' => TaskStatus::Failed,
        ]);

        $persona = $this->proposal->persona;

        try {
            app(TelegramService::class)->sendPlainMessage(
                "❌ Subtask Generation Failed\n\n"
                ."Persona: {$persona->name}\n"
                ."Proposal: {$this->proposal->title}\n"
                ."Error: {$reason}"
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to send Telegram failure notification', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Parse subtasks from Claude response text.
     * Expects JSON array in the response, either as a fenced block or inline.
     *
     * @return array<int, array{index: int, title: string, description: string, status: string}>
     */
    protected function parseSubtasks(string $text): array
    {
        // Try to find a JSON array in the text
        // First try fenced code blocks
        if (preg_match('/```(?:json)?\s*(\[[\s\S]*?\])\s*```/', $text, $matches)) {
            $parsed = json_decode($matches[1], true);
            if (is_array($parsed)) {
                return $this->normalizeSubtasks($parsed);
            }
        }

        // Try to find a raw JSON array
        if (preg_match('/\[[\s\S]*\]/', $text, $matches)) {
            $parsed = json_decode($matches[0], true);
            if (is_array($parsed)) {
                return $this->normalizeSubtasks($parsed);
            }
        }

        return [];
    }

    /**
     * Normalize parsed subtasks to ensure correct structure.
     *
     * @param  array<int, array<string, mixed>>  $subtasks
     * @return array<int, array{index: int, title: string, description: string, status: string}>
     */
    protected function normalizeSubtasks(array $subtasks): array
    {
        return collect($subtasks)
            ->values()
            ->map(fn (array $s, int $i): array => [
                'index' => $i,
                'title' => $s['title'] ?? "Subtask {$i}",
                'description' => $s['description'] ?? '',
                'status' => 'pending',
            ])
            ->all();
    }

    /**
     * Send Telegram notification with subtask list and approval buttons.
     *
     * @param  array<int, array{index: int, title: string, description: string, status: string}>  $subtasks
     */
    protected function sendSubtaskApprovalNotification(array $subtasks): void
    {
        $persona = $this->proposal->persona;
        $subtaskList = collect($subtasks)
            ->map(fn (array $s, int $i): string => ($i + 1).'. '.$s['title'])
            ->implode("\n");

        $text = "📋 Subtasks Generated\n\n"
            ."Persona: {$persona->name}\n"
            ."Proposal: {$this->proposal->title}\n\n"
            ."Subtasks:\n{$subtaskList}\n\n"
            .'Total: '.count($subtasks).' subtasks';

        $keyboard = [
            [
                ['text' => '✅ Approve Subtasks', 'callback_data' => "approve_subtasks:{$this->proposal->id}"],
                ['text' => '❌ Reject', 'callback_data' => "reject:{$this->proposal->id}"],
            ],
        ];

        try {
            app(TelegramService::class)->sendPlainMessage($text, $keyboard);
        } catch (\Throwable $e) {
            Log::warning('Failed to send subtask approval notification', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Build the prompt for subtask generation.
     */
    protected function buildPrompt(): string
    {
        $persona = $this->proposal->persona;
        $storageService = app(PersonaStorageService::class);
        $context = $storageService->readContext($persona) ?? '';

        $prompt = "## Task: Generate Subtasks for Proposal\n\n";
        $prompt .= "You are acting as the persona \"{$persona->name}\".\n\n";

        // Add proposal description
        $prompt .= "### Proposal Description\n{$this->proposal->description}\n\n";

        // Add data appendix if available
        if ($this->proposal->data_appendix) {
            $prompt .= "### Data Appendix\n{$this->proposal->data_appendix}\n\n";
        }

        // Add persona context
        if ($context) {
            $prompt .= "### Persona Context\n{$context}\n\n";
        }

        // Add persona master prompt guidance
        if ($persona->master_prompt) {
            $prompt .= "### Persona Guidelines\n{$persona->master_prompt}\n\n";
        }

        // Add MCP guidance
        if ($persona->mcp_guidance) {
            $prompt .= "### MCP Tools Guidance\n{$persona->mcp_guidance}\n\n";
        }

        $prompt .= "### Output Requirements\n";
        $prompt .= "Break down the proposal into concrete, actionable subtasks.\n";
        $prompt .= "Return ONLY a JSON array with the following structure:\n\n";
        $prompt .= "```json\n";
        $prompt .= "[\n";
        $prompt .= "  {\"title\": \"Short title\", \"description\": \"Detailed description of what to do\"},\n";
        $prompt .= "  {\"title\": \"Another task\", \"description\": \"Another detailed description\"}\n";
        $prompt .= "]\n";
        $prompt .= "```\n\n";
        $prompt .= "Each subtask should be:\n";
        $prompt .= "- Independent and self-contained where possible\n";
        $prompt .= "- Ordered logically (dependencies first)\n";
        $prompt .= "- Specific enough to be executed by an AI agent\n";
        $prompt .= "- Include all necessary context in the description\n";

        return $prompt;
    }

    protected function buildCommand(string $prompt): string
    {
        $escapedPrompt = escapeshellarg($prompt);
        $sessionId = escapeshellarg((string) Str::uuid());

        $claudeCmd = "/usr/bin/claude -p {$escapedPrompt} --output-format stream-json --verbose --dangerously-skip-permissions --session-id {$sessionId} --max-turns 50";

        // Add MCP config if available
        $mcpConfig = $this->getMcpConfig();
        if ($mcpConfig) {
            $claudeCmd .= ' --mcp-config '.escapeshellarg($mcpConfig);
        }

        $envVars = [
            'HOME' => getenv('HOME') ?: '/home/ploi',
            'PATH' => '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin',
            'USER' => 'ploi',
            'SHELL' => '/bin/bash',
            'TERM' => 'xterm-256color',
        ];

        // Add provider environment variables (API keys)
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

    protected function getMcpConfig(): ?string
    {
        $mcpConfigPath = base_path('.mcp.json');

        if (file_exists($mcpConfigPath)) {
            $config = json_decode(file_get_contents($mcpConfigPath), true);

            if (! empty($config)) {
                return json_encode($config);
            }
        }

        return json_encode([
            'mcpServers' => [
                'playwright' => [
                    'command' => 'npx',
                    'args' => ['@playwright/mcp@latest'],
                ],
            ],
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function parseLine(string $line): ?array
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
                }
            }
        }

        if (($data['type'] ?? '') === 'result') {
            $result['result'] = true;
            $result['cost_usd'] = $data['total_cost_usd'] ?? null;

            if (! empty($data['result'])) {
                $result['result_summary'] = $data['result'];
            }
        }

        return $result ?: null;
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('GeneratePersonaSubtasksJob failed permanently', [
            'proposal_id' => $this->proposal->id,
            'error' => $exception->getMessage(),
        ]);

        $this->handleFailure($exception->getMessage());
    }
}
