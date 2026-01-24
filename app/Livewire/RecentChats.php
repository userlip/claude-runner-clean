<?php

namespace App\Livewire;

use App\Filament\Resources\Tasks\TaskResource;
use App\Models\Repository;
use App\Models\Task;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

class RecentChats extends Component
{
    public bool $showSystemTasks = false;

    /**
     * Get recent chats (all tasks, including general chats).
     * Returns 10 items for desktop display.
     *
     * @return Collection<int, array{type: string, model: Task}>
     */
    #[Computed]
    public function recentChats(): Collection
    {
        $securityTaskIds = Repository::whereNotNull('security_task_id')
            ->pluck('security_task_id')
            ->toArray();

        return Task::query()
            ->where(function ($query) {
                // Tasks with repositories owned by the user
                $query->whereHas('repository', fn ($q) => $q->where('user_id', Auth::id()))
                    // OR general chats owned by the user
                    ->orWhere('user_id', Auth::id());
            })
            ->when(! $this->showSystemTasks && count($securityTaskIds) > 0, function ($query) use ($securityTaskIds) {
                $query->whereNotIn('id', $securityTaskIds);
            })
            ->latest('updated_at')
            ->limit(10)
            ->get()
            ->map(fn (Task $task) => [
                'type' => $task->isGeneralChat() ? 'general' : 'task',
                'model' => $task,
                'updated_at' => $task->updated_at,
            ]);
    }

    public function toggleSystemTasks(): void
    {
        $this->showSystemTasks = ! $this->showSystemTasks;
        unset($this->recentChats);
    }

    public function getChatUrl(array $chat): string
    {
        return TaskResource::getUrl('chat', ['record' => $chat['model']->uuid]);
    }

    public function getChatTitle(array $chat): string
    {
        return $chat['model']->title ?? 'Untitled';
    }

    public function getChatBadge(array $chat): ?string
    {
        if ($chat['type'] === 'task' && $chat['model']->repository) {
            return $chat['model']->repository->name;
        }

        return null;
    }

    public function hasUnreadReply(array $chat): bool
    {
        return $chat['model']->hasUnreadReply();
    }

    public function render()
    {
        return view('livewire.recent-chats');
    }
}
