<?php

namespace App\Livewire\Home;

use App\Enums\TaskStatus;
use App\Models\SecurityRun;
use App\Models\Task;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class Index extends Component
{
    #[Computed]
    public function recentChats(): Collection
    {
        $securityTaskIds = SecurityRun::whereNotNull('task_id')
            ->pluck('task_id')
            ->toArray();

        $limit = (int) Auth::user()->setting('recent_chats_limit', 10);

        return Task::query()
            ->with('repository')
            ->where(function ($query) {
                $query->whereHas('repository', fn ($q) => $q->where('user_id', Auth::id()))
                    ->orWhere('user_id', Auth::id());
            })
            ->when(count($securityTaskIds) > 0, fn ($q) => $q->whereNotIn('id', $securityTaskIds))
            ->where(function ($q) {
                $q->whereNull('title')
                    ->orWhere(function ($inner) {
                        $inner->where('title', 'not like', 'Security PR #%')
                            ->where('title', 'not like', 'Security Management:%')
                            ->where('title', 'not like', 'Major Upgrade:%');
                    });
            })
            ->latest('updated_at')
            ->limit($limit)
            ->get();
    }

    #[Computed]
    public function runningTasks(): Collection
    {
        return Task::query()
            ->with('repository')
            ->where(function ($query) {
                $query->whereHas('repository', fn ($q) => $q->where('user_id', Auth::id()))
                    ->orWhere('user_id', Auth::id());
            })
            ->whereIn('status', [TaskStatus::Running, TaskStatus::WaitingForInput])
            ->latest('updated_at')
            ->get();
    }

    #[On('recent-chats-updated')]
    public function refresh(): void
    {
        unset($this->recentChats, $this->runningTasks);
    }

    public function render(): View
    {
        return view('livewire.home.index');
    }
}
