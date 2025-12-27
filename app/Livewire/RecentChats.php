<?php

namespace App\Livewire;

use App\Filament\Resources\Tasks\TaskResource;
use App\Models\Task;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

class RecentChats extends Component
{
    /**
     * @return Collection<int, Task>
     */
    #[Computed]
    public function recentTasks(): Collection
    {
        return Task::query()
            ->latest('updated_at')
            ->limit(6)
            ->get();
    }

    public function getTaskUrl(Task $task): string
    {
        return TaskResource::getUrl('chat', ['record' => $task->uuid]);
    }

    public function render()
    {
        return view('livewire.recent-chats');
    }
}
