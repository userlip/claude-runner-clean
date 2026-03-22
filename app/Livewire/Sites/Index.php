<?php

namespace App\Livewire\Sites;

use App\Models\Site;
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
    public array $sortBy = ['column' => 'domain', 'direction' => 'asc'];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function delete(int $id): void
    {
        Site::findOrFail($id)->delete();
        $this->success('Site deleted.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function headers(): array
    {
        return [
            ['key' => 'domain', 'label' => 'Domain'],
            ['key' => 'branch', 'label' => 'Branch'],
            ['key' => 'php_version', 'label' => 'PHP Version'],
            ['key' => 'status', 'label' => 'Status'],
        ];
    }

    public function sites(): LengthAwarePaginator
    {
        return Site::query()
            ->when(
                $this->search,
                fn ($q) => $q->where('domain', 'like', "%{$this->search}%")
                    ->orWhere('branch', 'like', "%{$this->search}%")
            )
            ->orderBy($this->sortBy['column'], $this->sortBy['direction'])
            ->paginate(15);
    }

    public function render(): View
    {
        return view('livewire.sites.index', [
            'sites' => $this->sites(),
            'headers' => $this->headers(),
        ]);
    }
}
