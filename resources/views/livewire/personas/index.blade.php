<div>
    <x-header title="Personas" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-input placeholder="Search..." wire:model.live.debounce="search" clearable icon="o-magnifying-glass" />
        </x-slot:middle>
        <x-slot:actions>
            <x-button label="Create" icon="o-plus" link="{{ route('app.personas.create') }}" class="btn-primary" />
        </x-slot:actions>
    </x-header>

    <x-card shadow>
        <x-table :headers="$headers" :rows="$personas" :sort-by="$sortBy" with-pagination>
            @scope('cell_status', $persona)
                {{ $persona->status->label() }}
            @endscope

            @scope('cell_is_active', $persona)
                {{ $persona->is_active ? 'Yes' : 'No' }}
            @endscope

            @scope('actions', $persona)
                <x-button icon="o-pencil" link="{{ route('app.personas.edit', $persona->slug) }}" spinner class="btn-ghost btn-sm" />
                <x-button icon="o-trash" wire:click="delete({{ $persona->id }})" wire:confirm="Are you sure you want to delete this persona?" spinner class="btn-ghost btn-sm text-error" />
            @endscope
        </x-table>
    </x-card>
</div>
