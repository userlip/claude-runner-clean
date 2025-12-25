<?php

namespace App\Livewire;

use App\Enums\MessageRole;
use App\Jobs\RunClaudeMessageJob;
use App\Models\Message;
use App\Models\Site;
use App\Models\Task;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

class SiteChat extends Component
{
    public Site $site;

    public ?Task $activeTask = null;

    public string $prompt = '';

    public function mount(Site $site): void
    {
        $this->site = $site;
        $this->activeTask = $site->tasks()->latest()->first();
    }

    /**
     * @return Collection<int, Task>
     */
    #[Computed]
    public function tasks(): Collection
    {
        return $this->site->tasks()->latest()->get();
    }

    /**
     * @return Collection<int, Message>
     */
    #[Computed]
    public function chatMessages(): Collection
    {
        if (! $this->activeTask) {
            return new Collection;
        }

        return $this->activeTask->messages()->oldest()->get();
    }

    #[Computed]
    public function isRunning(): bool
    {
        return $this->activeTask?->isRunning() ?? false;
    }

    public function selectTask(int $taskId): void
    {
        $this->activeTask = Task::find($taskId);
    }

    public function newChat(): void
    {
        $this->activeTask = null;
        $this->prompt = '';
    }

    public function sendMessage(): void
    {
        $this->validate([
            'prompt' => 'required|string|min:1|max:10000',
        ]);

        if (! $this->activeTask) {
            $this->activeTask = Task::create([
                'site_id' => $this->site->id,
            ]);
        }

        $isFirstMessage = $this->activeTask->messages()->count() === 0;

        $userMessage = Message::create([
            'task_id' => $this->activeTask->id,
            'role' => MessageRole::User,
            'content' => $this->prompt,
        ]);

        RunClaudeMessageJob::dispatch(
            $this->activeTask,
            $userMessage,
            continue: ! $isFirstMessage
        );

        $this->prompt = '';
    }

    public function render()
    {
        return view('livewire.site-chat');
    }
}
