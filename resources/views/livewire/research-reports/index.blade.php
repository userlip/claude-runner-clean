<div>
    <x-header title="Research Reports" separator progress-indicator />

    <x-card shadow>
        <x-table :headers="$headers" :rows="$researchReports" :sort-by="$sortBy" with-pagination>
            @scope('cell_module', $report)
                {{ $report->module->label() }}
            @endscope

            @scope('cell_created_at', $report)
                {{ $report->created_at->format('Y-m-d') }}
            @endscope

            @scope('actions', $report)
                <x-button icon="o-eye" link="{{ route('app.research-reports.show', $report->uuid) }}" spinner class="btn-ghost btn-sm" />
            @endscope
        </x-table>
    </x-card>
</div>
