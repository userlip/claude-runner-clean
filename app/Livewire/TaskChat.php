<?php

namespace App\Livewire;

use App\Enums\MessageRole;
use App\Jobs\RunClaudeMessageJob;
use App\Models\Message;
use App\Models\Task;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\File;
use Livewire\Attributes\Computed;
use Livewire\Component;

class TaskChat extends Component
{
    public Task $task;

    public string $prompt = '';

    public function mount(Task $task): void
    {
        $this->task = $task;
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
    public function locationLabel(): string
    {
        if ($this->task->site) {
            return $this->task->site->domain;
        }

        return 'Workspace';
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

    public function render()
    {
        return view('livewire.task-chat');
    }
}
