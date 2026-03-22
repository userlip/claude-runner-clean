<?php

namespace App\Livewire\Schedules;

use App\Models\TaskSchedule;
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

    /** @var array{column: string, direction: string} */
    public array $sortBy = ['column' => 'name', 'direction' => 'asc'];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function toggleActive(int $id): void
    {
        $schedule = TaskSchedule::findOrFail($id);
        $schedule->update(['is_active' => ! $schedule->is_active]);
        $this->success($schedule->is_active ? 'Schedule activated.' : 'Schedule deactivated.');
    }

    public function delete(int $id): void
    {
        TaskSchedule::findOrFail($id)->delete();
        $this->success('Schedule deleted.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function headers(): array
    {
        return [
            ['key' => 'name', 'label' => 'Name'],
            ['key' => 'cron_expression', 'label' => 'Cron'],
            ['key' => 'is_active', 'label' => 'Active', 'sortable' => false],
            ['key' => 'last_run_at', 'label' => 'Last Run'],
        ];
    }

    public function schedules(): LengthAwarePaginator
    {
        return TaskSchedule::query()
            ->when(
                $this->search,
                fn ($q) => $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('cron_expression', 'like', "%{$this->search}%")
            )
            ->orderBy($this->sortBy['column'], $this->sortBy['direction'])
            ->paginate(15);
    }

    public function render(): View
    {
        return view('livewire.schedules.index', [
            'schedules' => $this->schedules(),
            'headers' => $this->headers(),
        ]);
    }
}
