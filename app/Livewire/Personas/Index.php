<?php

namespace App\Livewire\Personas;

use App\Models\Persona;
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
        Persona::findOrFail($id)->delete();
        $this->success('Persona deleted.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function headers(): array
    {
        return [
            ['key' => 'name', 'label' => 'Name'],
            ['key' => 'slug', 'label' => 'Slug'],
            ['key' => 'status', 'label' => 'Status', 'sortable' => false],
            ['key' => 'is_active', 'label' => 'Active', 'sortable' => false],
            ['key' => 'total_runs', 'label' => 'Runs'],
            ['key' => 'total_proposals', 'label' => 'Proposals'],
        ];
    }

    public function personas(): LengthAwarePaginator
    {
        return Persona::query()
            ->when(
                $this->search,
                fn ($q) => $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('slug', 'like', "%{$this->search}%")
            )
            ->orderBy($this->sortBy['column'], $this->sortBy['direction'])
            ->paginate(15);
    }

    public function render(): View
    {
        return view('livewire.personas.index', [
            'personas' => $this->personas(),
            'headers' => $this->headers(),
        ]);
    }
}
