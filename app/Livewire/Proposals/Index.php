<?php

namespace App\Livewire\Proposals;

use App\Models\Proposal;
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
    public array $sortBy = ['column' => 'title', 'direction' => 'asc'];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function delete(int $id): void
    {
        Proposal::findOrFail($id)->delete();
        $this->success('Proposal deleted.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function headers(): array
    {
        return [
            ['key' => 'title', 'label' => 'Title'],
            ['key' => 'project', 'label' => 'Project'],
            ['key' => 'type', 'label' => 'Type', 'sortable' => false],
            ['key' => 'priority', 'label' => 'Priority', 'sortable' => false],
            ['key' => 'status', 'label' => 'Status', 'sortable' => false],
        ];
    }

    public function proposals(): LengthAwarePaginator
    {
        return Proposal::query()
            ->when(
                $this->search,
                fn ($q) => $q->where('title', 'like', "%{$this->search}%")
                    ->orWhere('project', 'like', "%{$this->search}%")
            )
            ->orderBy($this->sortBy['column'], $this->sortBy['direction'])
            ->paginate(15);
    }

    public function render(): View
    {
        return view('livewire.proposals.index', [
            'proposals' => $this->proposals(),
            'headers' => $this->headers(),
        ]);
    }
}
