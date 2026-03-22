<?php

namespace App\Livewire\MajorUpgradeRuns;

use App\Models\MajorUpgradeRun;
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
            ['key' => 'github_pr_number', 'label' => 'PR #'],
            ['key' => 'status', 'label' => 'Status', 'sortable' => false],
            ['key' => 'created_at', 'label' => 'Date'],
        ];
    }

    public function majorUpgradeRuns(): LengthAwarePaginator
    {
        return MajorUpgradeRun::query()
            ->with('repository')
            ->orderBy($this->sortBy['column'], $this->sortBy['direction'])
            ->paginate(15);
    }

    public function render(): View
    {
        return view('livewire.major-upgrade-runs.index', [
            'majorUpgradeRuns' => $this->majorUpgradeRuns(),
            'headers' => $this->headers(),
        ]);
    }
}
