<?php

namespace App\Livewire\PromotionDirectories;

use App\Models\PromotionDirectory;
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
        PromotionDirectory::findOrFail($id)->delete();
        $this->success('Directory deleted.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function headers(): array
    {
        return [
            ['key' => 'name', 'label' => 'Name'],
            ['key' => 'url', 'label' => 'URL'],
            ['key' => 'category', 'label' => 'Category', 'sortable' => false],
            ['key' => 'submission_type', 'label' => 'Submission Type'],
        ];
    }

    public function directories(): LengthAwarePaginator
    {
        return PromotionDirectory::query()
            ->when(
                $this->search,
                fn ($q) => $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('url', 'like', "%{$this->search}%")
            )
            ->orderBy($this->sortBy['column'], $this->sortBy['direction'])
            ->paginate(15);
    }

    public function render(): View
    {
        return view('livewire.promotion-directories.index', [
            'directories' => $this->directories(),
            'headers' => $this->headers(),
        ]);
    }
}
