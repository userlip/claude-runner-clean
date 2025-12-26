<?php

namespace App\Livewire;

use App\Enums\MessageRole;
use App\Jobs\RunClaudeMessageJob;
use App\Models\AiProvider;
use App\Models\Message;
use App\Models\Task;
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

    #[Computed]
    public function shouldPoll(): bool
    {
        // Check if we got a response (message count increased)
        $currentCount = $this->task->messages()->count();
        if ($this->waitingForResponse && $currentCount > $this->lastMessageCount) {
            $this->waitingForResponse = false;
            $this->lastMessageCount = $currentCount;
        }

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
        $this->validate([
            'prompt' => 'required|string|min:1|max:10000',
        ]);

        $isFirstMessage = $this->task->messages()->count() === 0;

        $userMessage = Message::create([
            'task_id' => $this->task->id,
            'role' => MessageRole::User,
            'content' => $this->prompt,
        ]);

        RunClaudeMessageJob::dispatch(
            $this->task,
            $userMessage,
            continue: ! $isFirstMessage
        );

        $this->prompt = '';
        $this->waitingForResponse = true;
        $this->lastMessageCount = $this->task->messages()->count();
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

    public function render()
    {
        return view('livewire.task-chat');
    }
}
