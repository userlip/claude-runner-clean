<?php

namespace App\Livewire;

use App\Filament\Resources\GeneralChatResource;
use App\Filament\Resources\Tasks\TaskResource;
use App\Models\GeneralChat;
use App\Models\Task;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

class RecentChats extends Component
{
    /**
     * Get combined recent chats from both Task and GeneralChat models.
     *
     * @return Collection<int, array{type: string, model: Task|GeneralChat}>
     */
    #[Computed]
    public function recentChats(): Collection
    {
        $tasks = Task::query()
            ->latest('updated_at')
            ->limit(6)
            ->get()
            ->map(fn (Task $task) => [
                'type' => 'task',
                'model' => $task,
                'updated_at' => $task->updated_at,
            ]);

        $generalChats = GeneralChat::query()
            ->latest('updated_at')
            ->limit(6)
            ->get()
            ->map(fn (GeneralChat $chat) => [
                'type' => 'general',
                'model' => $chat,
                'updated_at' => $chat->updated_at,
            ]);

        return $tasks->concat($generalChats)
            ->sortByDesc('updated_at')
            ->take(6)
            ->values();
    }

    public function getChatUrl(array $chat): string
    {
        if ($chat['type'] === 'task') {
            return TaskResource::getUrl('chat', ['record' => $chat['model']->uuid]);
        }

        return GeneralChatResource::getUrl('chat', ['record' => $chat['model']->uuid]);
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

    public function render()
    {
        return view('livewire.recent-chats');
    }
}
