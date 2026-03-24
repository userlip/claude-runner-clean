<?php

namespace App\Jobs;

use App\Enums\MessageRole;
use App\Enums\TaskStatus;
use App\Models\Message;
use App\Models\Proposal;
use App\Models\Task;
use App\Services\McpConfigService;
use App\Services\PersonaCycleService;
use App\Services\PersonaStorageService;
use App\Services\TelegramService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RunPersonaSubtaskJob implements ShouldQueue
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

        $subtask = $this->proposal->getCurrentSubtask();

        if (! $subtask) {
            Log::warning('No current subtask found', [
                'proposal_id' => $this->proposal->id,
                'current_index' => $this->proposal->current_subtask_index,
            ]);

            return;
        }

        $persona = $this->proposal->persona;

        Log::info('Running persona subtask', [
            'proposal_id' => $this->proposal->id,
            'subtask_index' => $this->proposal->current_subtask_index,
            'subtask_title' => $subtask['title'],
            'persona' => $persona->name,
        ]);

        $this->task->markAsRunning();
        $this->proposal->updateSubtaskStatus($this->proposal->current_subtask_index, 'running');

        $prompt = $this->buildPrompt($subtask);

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
            $resultReceived = false;

            while (! feof($pipes[1])) {
                $line = fgets($pipes[1]);
                if ($line === false) {
                    $meta = stream_get_meta_data($pipes[1]);
                    if ($meta['timed_out']) {
                        Log::error('Claude stdout stream timed out for subtask', [
                            'task_id' => $this->task->id,
                            'subtask_index' => $this->proposal->current_subtask_index,
                        ]);

                        break;
                    }

                    continue;
                }

                $output .= $line;
                $parsed = $this->parseLine($line);

                if ($parsed) {
                    if (isset($parsed['content'])) {
                        $assistantMessage->update([
                            'content' => ($assistantMessage->content ?? '').$parsed['content'],
                        ]);
                    }

                    if (isset($parsed['result'])) {
                        if (isset($parsed['result_summary'])) {
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
                $this->handleSuccess();
            } else {
                $this->handleFailure('Claude process ended without result event');
            }
        } catch (\Throwable $e) {
            Log::error('Persona subtask execution failed', [
                'proposal_id' => $this->proposal->id,
                'subtask_index' => $this->proposal->current_subtask_index,
                'error' => $e->getMessage(),
            ]);

            $assistantMessage->update([
                'content' => "Error: {$e->getMessage()}",
            ]);

            $this->handleFailure($e->getMessage());

            throw $e;
        }
    }

    protected function handleSuccess(): void
    {
        $currentIndex = $this->proposal->current_subtask_index;
        $this->proposal->updateSubtaskStatus($currentIndex, 'completed');

        $totalSubtasks = count($this->proposal->subtasks);
        $nextIndex = $currentIndex + 1;

        if ($nextIndex >= $totalSubtasks) {
            // All subtasks complete
            $this->task->update([
                'status' => TaskStatus::Completed,
                'completed_at' => now(),
            ]);

            app(PersonaCycleService::class)->completeExecution($this->proposal->fresh());
        } else {
            // Advance to next subtask
            $this->proposal->advanceSubtaskIndex();
            $this->proposal->refresh();
            $this->proposal->updateSubtaskStatus($nextIndex, 'running');

            // Dispatch next subtask job
            self::dispatch($this->proposal->fresh(), $this->task);
        }
    }

    protected function handleFailure(string $reason): void
    {
        $currentIndex = $this->proposal->current_subtask_index;
        $this->proposal->updateSubtaskStatus($currentIndex, 'failed');

        $this->task->update([
            'status' => TaskStatus::WaitingForInput,
        ]);

        $persona = $this->proposal->persona;
        $subtask = $this->proposal->getCurrentSubtask();

        try {
            app(TelegramService::class)->sendPlainMessage(
                "❌ Persona Subtask Failed\n\n"
                ."Persona: {$persona->name}\n"
                ."Proposal: {$this->proposal->title}\n"
                ."Subtask: {$subtask['title']}\n"
                ."Error: {$reason}"
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to send Telegram failure notification', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Build the prompt for this subtask combining subtask instructions, persona context, and previous results.
     */
    protected function buildPrompt(array $subtask): string
    {
        $persona = $this->proposal->persona;
        $storageService = app(PersonaStorageService::class);
        $context = $storageService->readContext($persona) ?? '';

        $prompt = "## Subtask: {$subtask['title']}\n\n";
        $prompt .= "### Instructions\n{$subtask['description']}\n\n";

        // Add persona context
        if ($context) {
            $prompt .= "### Persona Context\n{$context}\n\n";
        }

        // Add persona master prompt guidance
        if ($persona->master_prompt) {
            $prompt .= "### Persona Guidelines\n{$persona->master_prompt}\n\n";
        }

        // Add previous subtask results
        $previousResults = $this->getPreviousSubtaskResults();
        if ($previousResults) {
            $prompt .= "### Previous Subtask Results\n{$previousResults}\n\n";
        }

        // Add overall proposal context
        if ($this->proposal->description) {
            $prompt .= "### Overall Proposal\n{$this->proposal->description}\n\n";
        }

        if ($this->proposal->data_appendix) {
            $prompt .= "### Data Appendix\n{$this->proposal->data_appendix}\n\n";
        }

        $prompt .= "### Requirements\n";
        $prompt .= "1. Complete the subtask described above\n";
        $prompt .= "2. Commit your changes with descriptive messages\n";
        $prompt .= "3. Report completion status when done\n";

        return $prompt;
    }

    protected function getPreviousSubtaskResults(): ?string
    {
        $currentIndex = $this->proposal->current_subtask_index;

        if ($currentIndex === 0) {
            return null;
        }

        $subtasks = $this->proposal->subtasks;
        $lines = [];

        for ($i = 0; $i < $currentIndex; $i++) {
            $s = $subtasks[$i];
            $lines[] = "- **{$s['title']}**: {$s['status']}";
        }

        return implode("\n", $lines);
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
        return app(McpConfigService::class)->jsonForCli([
            'playwright' => [
                'command' => 'npx',
                'args' => ['@playwright/mcp@latest'],
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
        Log::error('RunPersonaSubtaskJob failed permanently', [
            'proposal_id' => $this->proposal->id,
            'subtask_index' => $this->proposal->current_subtask_index,
            'error' => $exception->getMessage(),
        ]);

        $this->handleFailure($exception->getMessage());
    }
}
