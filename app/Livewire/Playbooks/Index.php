<?php

namespace App\Livewire\Playbooks;

use App\Models\Playbook;
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

    public function delete(int $id): void
    {
        Playbook::findOrFail($id)->delete();
        $this->success('Playbook deleted.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function headers(): array
    {
        return [
            ['key' => 'name', 'label' => 'Name'],
            ['key' => 'proposal_type', 'label' => 'Type', 'sortable' => false],
            ['key' => 'project', 'label' => 'Project'],
            ['key' => 'is_active', 'label' => 'Active', 'sortable' => false],
            ['key' => 'times_used', 'label' => 'Used'],
        ];
    }

    public function playbooks(): LengthAwarePaginator
    {
        return Playbook::query()
            ->when(
                $this->search,
                fn ($q) => $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('project', 'like', "%{$this->search}%")
            )
            ->orderBy($this->sortBy['column'], $this->sortBy['direction'])
            ->paginate(15);
    }

    public function render(): View
    {
        return view('livewire.playbooks.index', [
            'playbooks' => $this->playbooks(),
            'headers' => $this->headers(),
        ]);
    }
}
