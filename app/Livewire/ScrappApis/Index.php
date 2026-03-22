<?php

namespace App\Livewire\ScrappApis;

use App\Models\ScrappApi;
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
        ScrappApi::findOrFail($id)->delete();
        $this->success('API deleted.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function headers(): array
    {
        return [
            ['key' => 'name', 'label' => 'Name'],
            ['key' => 'slug', 'label' => 'Slug'],
            ['key' => 'route_prefix', 'label' => 'Route Prefix'],
            ['key' => 'is_active', 'label' => 'Active'],
            ['key' => 'last_test_result', 'label' => 'Last Test', 'sortable' => false],
        ];
    }

    public function scrappApis(): LengthAwarePaginator
    {
        return ScrappApi::query()
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
        return view('livewire.scrapp-apis.index', [
            'scrappApis' => $this->scrappApis(),
            'headers' => $this->headers(),
        ]);
    }
}
