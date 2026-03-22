<?php

namespace App\Livewire\SecurityRuns;

use App\Models\SecurityRun;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    /** @var array{column: string, direction: string} */
    public array $sortBy = ['column' => 'created_at', 'direction' => 'desc'];

    /**
     * @return array<int, array<string, mixed>>
     */
    public function headers(): array
    {
        return [
            ['key' => 'id', 'label' => '#'],
            ['key' => 'repository_id', 'label' => 'Repository', 'sortable' => false],
            ['key' => 'pr_title', 'label' => 'PR Title', 'sortable' => false],
            ['key' => 'risk_level', 'label' => 'Risk', 'sortable' => false],
            ['key' => 'status', 'label' => 'Status', 'sortable' => false],
            ['key' => 'created_at', 'label' => 'Date'],
        ];
    }

    public function securityRuns(): LengthAwarePaginator
    {
        return SecurityRun::query()
            ->with('repository')
            ->orderBy($this->sortBy['column'], $this->sortBy['direction'])
            ->paginate(15);
    }

    public function render(): View
    {
        return view('livewire.security-runs.index', [
            'securityRuns' => $this->securityRuns(),
            'headers' => $this->headers(),
        ]);
    }
}
