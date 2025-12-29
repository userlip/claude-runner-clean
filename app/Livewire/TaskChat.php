<?php

namespace App\Livewire;

use App\Enums\MessageRole;
use App\Jobs\RunClaudeMessageJob;
use App\Models\AiProvider;
use App\Models\Message;
use App\Models\RepositoryEnvConfig;
use App\Models\Task;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\File;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class TaskChat extends Component
{
    public Task $task;

    public string $prompt = '';

    /** @var array<int, array{data: string, name: string}> */
    public array $images = [];

    public bool $waitingForResponse = false;

    public int $lastMessageCount = 0;

    public bool $showDeployModal = false;

    public string $deploySubdomain = '';

    public string $deployPhpVersion = '8.4';

    public string $deployWebDirectory = '/public';

    public ?string $deployDatabaseName = null;

    public bool $showAdvancedOptions = false;

    public function mount(Task $task): void
    {
        $this->task = $task;
        $this->lastMessageCount = $task->messages()->count();
        $task->markAsViewed();
    }

    #[On('insert-snippet')]
    public function insertSnippet(string $content): void
    {
        if (! empty($this->prompt)) {
            $this->prompt .= "\n\n";
        }
        $this->prompt .= $content;
    }

    /**
     * @return Collection<int, Message>
     */
    #[Computed]
    public function chatMessages(): Collection
    {
        return $this->task->messages()->oldest()->get();
    }

    #[Computed]
    public function isRunning(): bool
    {
        return $this->task->isRunning();
    }

    /**
     * Called by wire:poll to check if we should continue polling.
     * This method updates the waitingForResponse state.
     */
    public function checkPolling(): void
    {
        // Refresh task status
        $this->task->refresh();

        // Only stop waiting when task is no longer running
        // (message count check is unreliable since empty assistant message is created immediately)
        if ($this->waitingForResponse && ! $this->task->isRunning()) {
            $this->waitingForResponse = false;
            $this->lastMessageCount = $this->task->messages()->count();
        }
    }

    #[Computed]
    public function shouldPoll(): bool
    {
        return $this->isRunning || $this->waitingForResponse;
    }

    #[Computed]
    public function locationLabel(): string
    {
        if ($this->task->site) {
            return $this->task->site->domain;
        }

        return 'Workspace';
    }

    /**
     * @return EloquentCollection<int, AiProvider>
     */
    #[Computed]
    public function availableProviders(): EloquentCollection
    {
        return AiProvider::where('is_active', true)->get();
    }

    #[Computed]
    public function currentProvider(): ?AiProvider
    {
        return $this->task->aiProvider;
    }

    #[Computed]
    public function envConfigs(): EloquentCollection
    {
        if (! $this->task->repository) {
            return new EloquentCollection;
        }

        return $this->task->repository->envConfigs()->orderByDesc('is_default')->get();
    }

    #[Computed]
    public function hasEnvConfigs(): bool
    {
        return $this->envConfigs->isNotEmpty();
    }

    #[Computed]
    public function defaultEnvConfig(): ?RepositoryEnvConfig
    {
        return $this->envConfigs->firstWhere('is_default', true);
    }

    #[Computed]
    public function contextUsed(): int
    {
        $lastAssistantMessage = $this->task->messages()
            ->where('role', MessageRole::Assistant)
            ->whereNotNull('tokens_in')
            ->latest()
            ->first();

        return $lastAssistantMessage?->tokens_in ?? 0;
    }

    #[Computed]
    public function contextLimit(): int
    {
        return $this->task->aiProvider?->getContextWindow() ?? 200000;
    }

    #[Computed]
    public function contextPercentage(): float
    {
        if ($this->contextLimit === 0) {
            return 0;
        }

        return ($this->contextUsed / $this->contextLimit) * 100;
    }

    #[Computed]
    public function contextColor(): string
    {
        $percentage = $this->contextPercentage;

        if ($percentage >= 80) {
            return 'bg-red-500';
        }

        if ($percentage >= 60) {
            return 'bg-amber-500';
        }

        return 'bg-green-500';
    }

    public function setProvider(int $providerId): void
    {
        $provider = AiProvider::where('is_active', true)->find($providerId);

        if ($provider) {
            $this->task->update(['ai_provider_id' => $provider->id]);
            $this->task->refresh();
        }
    }

    public function sendMessage(): void
    {
        // Allow sending with just images (no text required)
        $hasContent = ! empty(trim($this->prompt)) || ! empty($this->images);

        if (! $hasContent) {
            return;
        }

        $this->validate([
            'prompt' => 'nullable|string',
            'images' => 'array|max:10',
            'images.*.data' => 'required|string',
            'images.*.name' => 'required|string|max:255',
        ]);

        // Handle slash commands locally
        if (! empty($this->prompt) && $this->handleSlashCommand($this->prompt)) {
            $this->prompt = '';
            $this->images = [];

            return;
        }

        // Check if there's a successful assistant response to continue from
        $hasSuccessfulResponse = $this->task->messages()
            ->where('role', MessageRole::Assistant)
            ->whereNotNull('content')
            ->where('content', '!=', '')
            ->where('content', 'not like', 'Error:%')
            ->exists();

        $userMessage = Message::create([
            'task_id' => $this->task->id,
            'role' => MessageRole::User,
            'content' => $this->prompt ?: '',
            'images' => ! empty($this->images) ? $this->images : null,
        ]);

        RunClaudeMessageJob::dispatch(
            $this->task,
            $userMessage,
            continue: $hasSuccessfulResponse
        );

        $this->prompt = '';
        $this->images = [];
        $this->waitingForResponse = true;
        $this->lastMessageCount = $this->task->messages()->count();
    }

    /**
     * Handle slash commands locally without sending to Claude.
     */
    protected function handleSlashCommand(string $prompt): bool
    {
        $command = strtolower(trim($prompt));

        if ($command === '/usage') {
            $this->handleUsageCommand();

            return true;
        }

        if ($command === '/clear') {
            $this->handleClearCommand();

            return true;
        }

        if ($command === '/help') {
            $this->handleHelpCommand();

            return true;
        }

        return false;
    }

    protected function handleUsageCommand(): void
    {
        $stats = $this->task->messages()
            ->where('role', MessageRole::Assistant)
            ->selectRaw('SUM(tokens_in) as total_in, SUM(tokens_out) as total_out, SUM(cost_usd) as total_cost')
            ->first();

        $totalIn = $stats->total_in ?? 0;
        $totalOut = $stats->total_out ?? 0;
        $totalCost = $stats->total_cost ?? 0;
        $messageCount = $this->task->messages()->count();

        $content = "## Session Usage\n\n";
        $content .= "| Metric | Value |\n";
        $content .= "|--------|-------|\n";
        $content .= "| Messages | {$messageCount} |\n";
        $content .= '| Input Tokens | '.number_format($totalIn)." |\n";
        $content .= '| Output Tokens | '.number_format($totalOut)." |\n";
        $content .= '| Total Tokens | '.number_format($totalIn + $totalOut)." |\n";
        $content .= '| Cost | $'.number_format($totalCost, 4)." |\n";

        // Create system message for usage
        Message::create([
            'task_id' => $this->task->id,
            'role' => MessageRole::User,
            'content' => '/usage',
        ]);

        Message::create([
            'task_id' => $this->task->id,
            'role' => MessageRole::Assistant,
            'content' => $content,
        ]);
    }

    protected function handleClearCommand(): void
    {
        $this->task->messages()->delete();
        $this->lastMessageCount = 0;

        $this->dispatch('notify', [
            'message' => 'Conversation cleared.',
        ]);
    }

    protected function handleHelpCommand(): void
    {
        $content = "## Available Commands\n\n";
        $content .= "| Command | Description |\n";
        $content .= "|---------|-------------|\n";
        $content .= "| `/usage` | Show token usage and cost for this session |\n";
        $content .= "| `/clear` | Clear all messages in this conversation |\n";
        $content .= "| `/help` | Show this help message |\n";

        Message::create([
            'task_id' => $this->task->id,
            'role' => MessageRole::User,
            'content' => '/help',
        ]);

        Message::create([
            'task_id' => $this->task->id,
            'role' => MessageRole::Assistant,
            'content' => $content,
        ]);
    }

    public function copyEnvConfig(?int $configId = null): void
    {
        if (! $this->task->workspace_path || ! is_dir($this->task->workspace_path)) {
            $this->dispatch('notify', [
                'message' => 'Workspace does not exist.',
                'type' => 'error',
            ]);

            return;
        }

        $config = $configId
            ? $this->task->repository->envConfigs()->find($configId)
            : $this->defaultEnvConfig;

        if (! $config) {
            $this->dispatch('notify', [
                'message' => 'No .env config found.',
                'type' => 'error',
            ]);

            return;
        }

        $envPath = $this->task->workspace_path.'/.env';
        file_put_contents($envPath, $config->content);

        $this->dispatch('notify', [
            'message' => "Copied '{$config->name}' .env to workspace.",
        ]);
    }

    public function deleteWorkspace(): void
    {
        if (! $this->task->workspace_path) {
            return;
        }

        if (File::isDirectory($this->task->workspace_path)) {
            File::deleteDirectory($this->task->workspace_path);
        }

        $this->task->update(['workspace_path' => null]);

        $this->dispatch('workspace-deleted');
    }

    public function openDeployModal(): void
    {
        $this->showDeployModal = true;
        $this->deploySubdomain = '';
    }

    public function closeDeployModal(): void
    {
        $this->showDeployModal = false;
    }

    public function deployToSite(): void
    {
        $this->validate([
            'deploySubdomain' => 'required|string|min:1|max:63|regex:/^[a-z0-9-]+$/',
        ]);

        \App\Jobs\DeployToSiteJob::dispatch(
            $this->task,
            $this->deploySubdomain,
            $this->deployPhpVersion,
            $this->deployWebDirectory,
            $this->deployDatabaseName,
        );

        $this->showDeployModal = false;

        $this->dispatch('notify', [
            'message' => "Deploying to {$this->deploySubdomain}.marin.sh...",
        ]);
    }

    public function generateTitle(): void
    {
        $messages = $this->task->messages()->oldest()->take(20)->get();

        if ($messages->isEmpty()) {
            Notification::make()
                ->title('No messages to generate title from')
                ->warning()
                ->send();

            return;
        }

        // Get first user message for context
        $firstUserMessage = $messages->first(fn ($m) => $m->role === MessageRole::User);
        $messageContent = $firstUserMessage?->content ?? '';
        // Truncate to first 300 chars
        $messageContent = substr($messageContent, 0, 300);

        $prompt = "Generate a 3-5 word title for a chat that starts with this message. Reply with ONLY the title, nothing else. No quotes, no explanation, no punctuation at the end.\n\nMessage: {$messageContent}\n\nTitle:";

        try {
            // Use Claude Code CLI which is already authenticated
            $claudePath = config('services.claude.path', '/usr/bin/claude');
            $escapedPrompt = escapeshellarg($prompt);

            // Run in temp dir to avoid picking up workspace context
            $process = proc_open(
                "{$claudePath} -p {$escapedPrompt} --output-format text --max-turns 1",
                [
                    0 => ['pipe', 'r'],
                    1 => ['pipe', 'w'],
                    2 => ['pipe', 'w'],
                ],
                $pipes,
                sys_get_temp_dir()
            );

            if (is_resource($process)) {
                fclose($pipes[0]);
                $output = stream_get_contents($pipes[1]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                $exitCode = proc_close($process);

                if ($exitCode === 0 && ! empty($output)) {
                    $title = trim($output, " \n\r\t\v\0\"'");
                    // Take only the first line in case Claude added extra content
                    $title = strtok($title, "\n");
                    // Remove any trailing punctuation
                    $title = rtrim($title, '.!?:');
                    // Limit to 50 chars max
                    if (strlen($title) > 50) {
                        $title = substr($title, 0, 50);
                    }

                    $this->task->update(['title' => $title]);
                    $this->task->refresh();

                    Notification::make()
                        ->title('Title updated')
                        ->body($title)
                        ->success()
                        ->send();
                } else {
                    Notification::make()
                        ->title('Failed to generate title')
                        ->body('Claude Code returned an error')
                        ->danger()
                        ->send();
                }
            } else {
                Notification::make()
                    ->title('Failed to start Claude Code')
                    ->danger()
                    ->send();
            }
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error generating title')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function render()
    {
        return view('livewire.task-chat');
    }
}
