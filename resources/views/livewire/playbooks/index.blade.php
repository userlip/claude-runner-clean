<div>
    <x-header title="Playbooks" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-input placeholder="Search..." wire:model.live.debounce="search" clearable icon="o-magnifying-glass" />
        </x-slot:middle>
        <x-slot:actions>
            <x-button label="Create" icon="o-plus" link="{{ route('app.playbooks.create') }}" class="btn-primary" />
        </x-slot:actions>
    </x-header>

    <x-card shadow>
        <x-table :headers="$headers" :rows="$playbooks" :sort-by="$sortBy" with-pagination>
            @scope('cell_proposal_type', $playbook)
                {{ $playbook->proposal_type->label() }}
            @endscope

            @scope('cell_is_active', $playbook)
                {{ $playbook->is_active ? 'Yes' : 'No' }}
            @endscope

            @scope('actions', $playbook)
                <x-button icon="o-pencil" link="{{ route('app.playbooks.edit', $playbook->id) }}" spinner class="btn-ghost btn-sm" />
                <x-button icon="o-trash" wire:click="delete({{ $playbook->id }})" wire:confirm="Are you sure you want to delete this playbook?" spinner class="btn-ghost btn-sm text-error" />
            @endscope
        </x-table>
    </x-card>
</div>
