<div>
    <x-header title="Proposals" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-input placeholder="Search..." wire:model.live.debounce="search" clearable icon="o-magnifying-glass" />
        </x-slot:middle>
        <x-slot:actions>
            <x-button label="Create" icon="o-plus" link="{{ route('app.proposals.create') }}" class="btn-primary" />
        </x-slot:actions>
    </x-header>

    <x-card shadow>
        <x-table :headers="$headers" :rows="$proposals" :sort-by="$sortBy" with-pagination>
            @scope('cell_type', $proposal)
                {{ $proposal->type->label() }}
            @endscope

            @scope('cell_priority', $proposal)
                {{ $proposal->priority->label() }}
            @endscope

            @scope('cell_status', $proposal)
                {{ $proposal->status->label() }}
            @endscope

            @scope('actions', $proposal)
                <x-button icon="o-pencil" link="{{ route('app.proposals.edit', $proposal->uuid) }}" spinner class="btn-ghost btn-sm" />
                <x-button icon="o-trash" wire:click="delete({{ $proposal->id }})" wire:confirm="Are you sure you want to delete this proposal?" spinner class="btn-ghost btn-sm text-error" />
            @endscope
        </x-table>
    </x-card>
</div>
