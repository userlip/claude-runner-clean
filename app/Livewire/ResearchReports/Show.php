<?php

namespace App\Livewire\ResearchReports;

use App\Models\ResearchReport;
use Illuminate\View\View;
use Livewire\Component;

class Show extends Component
{
    public ResearchReport $report;

    public function render(): View
    {
        return view('livewire.research-reports.show');
    }
}
