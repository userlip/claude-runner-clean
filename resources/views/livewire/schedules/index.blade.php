<div>
    <x-header title="Task Schedules" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-input placeholder="Search..." wire:model.live.debounce="search" clearable icon="o-magnifying-glass" />
        </x-slot:middle>
        <x-slot:actions>
            <x-button label="Create" icon="o-plus" link="{{ route('workbench.schedules.create') }}" class="btn-primary" />
        </x-slot:actions>
    </x-header>

    <x-card shadow>
        <x-table :headers="$headers" :rows="$schedules" :sort-by="$sortBy" with-pagination>
            @scope('cell_is_active', $schedule)
                <x-toggle wire:click="toggleActive({{ $schedule->id }})" :checked="$schedule->is_active" spinner />
            @endscope

            @scope('cell_last_run_at', $schedule)
                {{ $schedule->last_run_at?->diffForHumans() ?? 'Never' }}
            @endscope

            @scope('actions', $schedule)
                <div class="flex gap-1">
                    <x-button icon="o-pencil" link="{{ route('workbench.schedules.edit', $schedule->id) }}" spinner class="btn-ghost btn-sm" />
                    <x-button icon="o-trash" wire:click="delete({{ $schedule->id }})" wire:confirm="Are you sure you want to delete this schedule?" spinner class="btn-ghost btn-sm text-error" />
                </div>
            @endscope
        </x-table>
    </x-card>
</div>
