<?php

namespace App\Livewire\Tasks;

use App\Models\SecurityRun;
use App\Models\Task;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class Index extends Component
{
    use Toast;
    use WithPagination;

    public string $search = '';

    public bool $showSecurityTasks = false;

    /** @var array{column: string, direction: string} */
    public array $sortBy = ['column' => 'last_message_at', 'direction' => 'desc'];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedShowSecurityTasks(): void
    {
        $this->resetPage();
    }

    public function delete(int $id): void
    {
        Task::findOrFail($id)->delete();
        $this->success('Task deleted.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function headers(): array
    {
        return [
            ['key' => 'title', 'label' => 'Title'],
            ['key' => 'status_label', 'label' => 'Status', 'sortable' => false],
            ['key' => 'user_name', 'label' => 'User', 'sortable' => false],
            ['key' => 'last_message_at', 'label' => 'Last Message'],
            ['key' => 'created_at', 'label' => 'Created'],
        ];
    }

    public function tasks(): LengthAwarePaginator
    {
        $securityTaskIds = SecurityRun::whereNotNull('task_id')
            ->pluck('task_id')
            ->toArray();

        return Task::query()
            ->with(['user'])
            ->when(
                $this->search,
                fn ($q) => $q->where('title', 'like', "%{$this->search}%")
            )
            ->when(! $this->showSecurityTasks, function ($q) use ($securityTaskIds) {
                $q->when(count($securityTaskIds) > 0, fn ($inner) => $inner->whereNotIn('id', $securityTaskIds))
                    ->where(function ($inner) {
                        $inner->whereNull('title')
                            ->orWhere(function ($deep) {
                                $deep->where('title', 'not like', 'Security PR #%')
                                    ->where('title', 'not like', 'Security Management:%');
                            });
                    });
            })
            ->orderBy($this->sortBy['column'], $this->sortBy['direction'])
            ->paginate(15);
    }

    public function render(): View
    {
        $tasks = $this->tasks()->through(function (Task $task) {
            $task->status_label = $task->status?->label() ?? '-';
            $task->user_name = $task->user?->name ?? '-';

            return $task;
        });

        return view('livewire.tasks.index', [
            'tasks' => $tasks,
            'headers' => $this->headers(),
        ]);
    }
}
