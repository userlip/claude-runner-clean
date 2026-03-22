<div>
    <x-header title="Security Runs" separator progress-indicator />

    <x-card shadow>
        <x-table :headers="$headers" :rows="$securityRuns" :sort-by="$sortBy" with-pagination>
            @scope('cell_repository_id', $run)
                {{ $run->repository?->full_name ?? '-' }}
            @endscope

            @scope('cell_risk_level', $run)
                @if($run->risk_level)
                    <x-badge value="{{ $run->risk_level }}" />
                @else
                    -
                @endif
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
