<div>
    <x-header title="Major Upgrade Runs" separator progress-indicator />

    <x-card shadow>
        <x-table :headers="$headers" :rows="$majorUpgradeRuns" :sort-by="$sortBy" with-pagination>
            @scope('cell_repository_id', $run)
                {{ $run->repository?->full_name ?? '-' }}
            @endscope

            @scope('cell_status', $run)
                <x-badge value="{{ $run->status->value }}" />
            @endscope

            @scope('cell_created_at', $run)
                {{ $run->created_at->format('Y-m-d') }}
            @endscope
        </x-table>
    </x-card>
</div>
