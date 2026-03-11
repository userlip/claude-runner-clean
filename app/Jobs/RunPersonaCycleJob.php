<?php

namespace App\Jobs;

use App\Enums\MessageRole;
use App\Enums\PersonaStatus;
use App\Enums\TaskStatus;
use App\Models\AiProvider;
use App\Models\Message;
use App\Models\Persona;
use App\Models\Task;
use App\Services\PersonaCycleService;
use App\Services\TelegramService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RunPersonaCycleJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 10800; // 3 hours

    public int $tries = 1;

    public function __construct(
        public Persona $persona
    ) {}

    public function handle(): void
    {
        $this->persona->refresh();

        Log::info('Starting persona analysis cycle', [
            'persona_id' => $this->persona->id,
            'persona' => $this->persona->name,
            'total_runs' => $this->persona->total_runs,
        ]);

        $this->persona->update(['status' => PersonaStatus::Running]);

        $cycleService = app(PersonaCycleService::class);

        // Send start notification
        try {
            $cycleService->telegramService->sendPersonaCycleNotification(
                $this->persona->name,
                'started',
                'Running analysis cycle #'.($this->persona->total_runs + 1)
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to send cycle start notification', ['error' => $e->getMessage()]);
        }

        // Create workspace and task
        $repository = $this->persona->repository;
        $aiProvider = $this->persona->aiProvider
            ?? AiProvider::where('name', 'kimi')->where('is_active', true)->first()
            ?? AiProvider::getDefault();

        $workspacePath = '/home/ploi/workspaces/'.Str::slug($repository->name).'-'.Str::random(8);

        $task = Task::create([
            'title' => "[Persona Cycle] {$this->persona->name}",
            'status' => TaskStatus::Pending,
            'ai_provider_id' => $aiProvider?->id,
            'repository_id' => $repository->id,
            'workspace_path' => $workspacePath,
            'user_id' => $this->persona->user_id,
        ]);

        // Build prompt
        $prompt = $cycleService->buildAnalysisPrompt($this->persona);

        $task->messages()->create([
            'role' => MessageRole::User,
            'content' => $prompt,
        ]);

        $assistantMessage = Message::create([
            'task_id' => $task->id,
            'role' => MessageRole::Assistant,
            'content' => '',
        ]);

        try {
            $task->markAsRunning();

            // Clone repository first
            $this->cloneRepository($task);

            // Execute Claude
            $command = $this->buildCommand($prompt, $task);
            $workingDir = $task->working_directory;

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

            $resultText = '';
            $resultReceived = false;

            while (! feof($pipes[1])) {
                $line = fgets($pipes[1]);
                if ($line === false) {
                    $meta = stream_get_meta_data($pipes[1]);
                    if ($meta['timed_out']) {
                        Log::error('Claude stdout stream timed out for persona cycle', [
                            'task_id' => $task->id,
                            'persona_id' => $this->persona->id,
                        ]);

                        break;
                    }

                    continue;
                }

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
                $this->handleSuccess($cycleService, $task, $resultText);
            } else {
                $this->handleFailure($task, 'Claude process ended without result event');
            }
        } catch (\Throwable $e) {
            Log::error('Persona analysis cycle failed', [
                'persona_id' => $this->persona->id,
                'error' => $e->getMessage(),
            ]);

            $assistantMessage->update([
                'content' => "Error: {$e->getMessage()}",
            ]);

            $this->handleFailure($task, $e->getMessage());

            throw $e;
        } finally {
            // Clean up workspace
            if (isset($workspacePath) && File::isDirectory($workspacePath)) {
                File::deleteDirectory($workspacePath);
            }
        }
    }

    protected function handleSuccess(PersonaCycleService $cycleService, Task $task, string $resultText): void
    {
        $parsed = $cycleService->parseAnalysisOutput($resultText);

        $proposals = $cycleService->createProposalsFromAnalysis(
            $this->persona,
            $parsed['proposals'],
            $parsed['data_appendix']
        );

        $cycleNumber = ($this->persona->total_runs ?? 0) + 1;
        $lastProposal = end($proposals);

        // Log first proposal to history (contains the data appendix)
        $cycleService->logCycleToHistory($this->persona, $proposals[0], $cycleNumber);

        $this->persona->update([
            'last_run_at' => now(),
            'total_runs' => $cycleNumber,
            'total_proposals' => ($this->persona->total_proposals ?? 0) + count($proposals),
            'last_proposal_id' => $lastProposal->id,
            'status' => PersonaStatus::AwaitingApproval,
        ]);

        $task->update([
            'status' => TaskStatus::Completed,
            'completed_at' => now(),
        ]);

        Log::info('Persona analysis cycle completed', [
            'persona_id' => $this->persona->id,
            'proposal_count' => count($proposals),
            'cycle_number' => $cycleNumber,
        ]);

        // Send cycle completion notification
        try {
            $cycleService->telegramService->sendPersonaCycleNotification(
                $this->persona->name,
                'completed',
                count($proposals).' proposals created'
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to send cycle completion notification', ['error' => $e->getMessage()]);
        }

        // Send each proposal as a separate Telegram message
        foreach ($proposals as $proposal) {
            try {
                $cycleService->telegramService->sendProposalNotification($proposal);
            } catch (\Throwable $e) {
                Log::warning('Failed to send proposal notification', [
                    'proposal_id' => $proposal->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    protected function handleFailure(Task $task, string $reason): void
    {
        $task->update([
            'status' => TaskStatus::Failed,
        ]);

        $this->persona->update([
            'status' => PersonaStatus::Active,
        ]);

        try {
            app(TelegramService::class)->sendPlainMessage(
                "❌ Persona Analysis Cycle Failed\n\n"
                ."Persona: {$this->persona->name}\n"
                ."Error: {$reason}"
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to send Telegram failure notification', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Clone the repository into the workspace.
     */
    protected function cloneRepository(Task $task): void
    {
        $repository = $task->repository;

        if (! $repository) {
            return;
        }

        $workspacePath = $task->workspace_path;

        File::ensureDirectoryExists($workspacePath);

        $cloneUrl = $repository->clone_url ?? $repository->ssh_url ?? $repository->url;

        if (! $cloneUrl) {
            Log::warning('No clone URL for repository', ['repository_id' => $repository->id]);

            return;
        }

        $escapedUrl = escapeshellarg($cloneUrl);
        $escapedPath = escapeshellarg($workspacePath);

        $command = "git clone --depth 1 {$escapedUrl} {$escapedPath} 2>&1";
        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            Log::warning('Repository clone failed', [
                'repository_id' => $repository->id,
                'exit_code' => $exitCode,
                'output' => implode("\n", $output),
            ]);
        }
    }

    protected function buildCommand(string $prompt, Task $task): string
    {
        $escapedPrompt = escapeshellarg($prompt);
        $sessionId = escapeshellarg((string) Str::uuid());

        $claudeCmd = "/usr/bin/claude -p {$escapedPrompt} --output-format stream-json --verbose --dangerously-skip-permissions --session-id {$sessionId} --max-turns 50";

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

        $provider = $task->aiProvider;
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
        Log::error('RunPersonaCycleJob failed permanently', [
            'persona_id' => $this->persona->id,
            'error' => $exception->getMessage(),
        ]);

        $this->persona->update([
            'status' => PersonaStatus::Active,
        ]);

        try {
            app(TelegramService::class)->sendPlainMessage(
                "❌ Persona Analysis Cycle Failed\n\n"
                ."Persona: {$this->persona->name}\n"
                ."Error: {$exception->getMessage()}"
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to send failure notification', ['error' => $e->getMessage()]);
        }
    }
}
