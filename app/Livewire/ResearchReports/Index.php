<?php

namespace App\Livewire\ResearchReports;

use App\Models\ResearchReport;
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
            ['key' => 'title', 'label' => 'Title'],
            ['key' => 'module', 'label' => 'Module', 'sortable' => false],
            ['key' => 'findings_count', 'label' => 'Findings'],
            ['key' => 'proposals_created', 'label' => 'Proposals'],
            ['key' => 'created_at', 'label' => 'Date'],
        ];
    }

    public function researchReports(): LengthAwarePaginator
    {
        return ResearchReport::query()
            ->orderBy($this->sortBy['column'], $this->sortBy['direction'])
            ->paginate(15);
    }

    public function render(): View
    {
        return view('livewire.research-reports.index', [
            'researchReports' => $this->researchReports(),
            'headers' => $this->headers(),
        ]);
    }
}
