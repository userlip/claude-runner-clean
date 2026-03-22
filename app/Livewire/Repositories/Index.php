<?php

namespace App\Livewire\Repositories;

use App\Models\Repository;
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
        Repository::findOrFail($id)->delete();
        $this->success('Repository deleted.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function headers(): array
    {
        return [
            ['key' => 'full_name', 'label' => 'Repository'],
            ['key' => 'default_branch', 'label' => 'Default Branch'],
            ['key' => 'value_tier', 'label' => 'Value Tier'],
        ];
    }

    public function repositories(): LengthAwarePaginator
    {
        return Repository::query()
            ->when(
                $this->search,
                fn ($q) => $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('full_name', 'like', "%{$this->search}%")
                    ->orWhere('description', 'like', "%{$this->search}%")
            )
            ->orderBy($this->sortBy['column'], $this->sortBy['direction'])
            ->paginate(15);
    }

    public function render(): View
    {
        return view('livewire.repositories.index', [
            'repositories' => $this->repositories(),
            'headers' => $this->headers(),
        ]);
    }
}
