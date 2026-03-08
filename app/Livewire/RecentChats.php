<?php

namespace App\Livewire;

use App\Enums\MessageRole;
use App\Enums\TaskStatus;
use App\Filament\Resources\Tasks\TaskResource;
use App\Models\SecurityRun;
use App\Models\Task;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

class RecentChats extends Component
{
    public bool $showSystemTasks = false;

    /**
     * Pre-computed unread status for each task to avoid N+1 queries.
     *
     * @var array<int, bool>
     */
    public array $unreadStatus = [];

    /**
     * Get recent chats (all tasks, including general chats).
     * Returns 10 items for desktop display.
     *
     * @return Collection<int, array{type: string, model: Task}>
     */
    #[Computed]
    public function recentChats(): Collection
    {
        $securityTaskIds = SecurityRun::whereNotNull('task_id')
            ->pluck('task_id')
            ->toArray();

        $tasks = Task::query()
            ->with('repository')
            ->where(function ($query) {
                $query->whereHas('repository', fn ($q) => $q->where('user_id', Auth::id()))
                    ->orWhere('user_id', Auth::id());
            })
            ->when(! $this->showSystemTasks, function ($query) use ($securityTaskIds) {
                $query->when(count($securityTaskIds) > 0, fn ($q) => $q->whereNotIn('id', $securityTaskIds));
                $query->where(function ($q) {
                    $q->whereNull('title')
                        ->orWhere(function ($inner) {
                            $inner->where('title', 'not like', 'Security PR #%')
                                ->where('title', 'not like', 'Security Management:%')
                                ->where('title', 'not like', 'Major Upgrade:%');
                        });
                });
            })
            ->latest('updated_at')
            ->limit(10)
            ->get();

        // Batch-compute unread status in a single query instead of N+1
        $this->unreadStatus = $this->computeUnreadStatus($tasks);

        return $tasks->map(fn (Task $task) => [
            'type' => $task->isGeneralChat() ? 'general' : 'task',
            'model' => $task,
            'updated_at' => $task->updated_at,
        ]);
    }

    /**
     * Compute unread status for all tasks in a single query.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, Task>  $tasks
     * @return array<int, bool>
     */
    protected function computeUnreadStatus(\Illuminate\Database\Eloquent\Collection $tasks): array
    {
        if ($tasks->isEmpty()) {
            return [];
        }

        $status = [];

        // Split tasks into "never viewed" and "has last_viewed_at"
        $neverViewed = $tasks->whereNull('last_viewed_at');
        $viewed = $tasks->whereNotNull('last_viewed_at')->filter(fn ($t) => ! $t->isRunning());

        // For never-viewed tasks: check if any assistant message exists
        if ($neverViewed->isNotEmpty()) {
            $hasAssistant = \DB::table('messages')
                ->whereIn('task_id', $neverViewed->pluck('id'))
                ->where('role', MessageRole::Assistant->value)
                ->groupBy('task_id')
                ->pluck('task_id')
                ->flip()
                ->all();

            foreach ($neverViewed as $task) {
                $status[$task->id] = isset($hasAssistant[$task->id]);
            }
        }

        // For viewed tasks (not running): check if assistant message after last_viewed_at
        if ($viewed->isNotEmpty()) {
            // Build a union of conditions per task
            $taskIds = $viewed->pluck('id')->all();
            $unreadTaskIds = [];

            // Single query with CASE-based check
            foreach ($viewed as $task) {
                $hasNewer = \DB::table('messages')
                    ->where('task_id', $task->id)
                    ->where('role', MessageRole::Assistant->value)
                    ->where('created_at', '>', $task->last_viewed_at)
                    ->exists();

                $unreadTaskIds[$task->id] = $hasNewer;
            }

            $status = $status + $unreadTaskIds;
        }

        // Running tasks are never "unread"
        foreach ($tasks->filter(fn ($t) => $t->isRunning() && $t->last_viewed_at !== null) as $task) {
            $status[$task->id] = false;
        }

        return $status;
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

    public function getChatStatusClass(array $chat): string
    {
        $task = $chat['model'];

        if (
            $task->ralph_enabled
            && ! $task->ralph_stopped_reason
            && ! in_array($task->status, [TaskStatus::Completed, TaskStatus::Failed], true)
        ) {
            return 'recent-chat-status-ralph';
        }

        return 'recent-chat-status-'.$task->status->value;
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
        return $this->unreadStatus[$chat['model']->id] ?? false;
    }

    public function render()
    {
        return view('livewire.recent-chats');
    }
}
