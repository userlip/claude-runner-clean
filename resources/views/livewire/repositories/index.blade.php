<div>
    <x-header title="Repositories" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-input placeholder="Search..." wire:model.live.debounce="search" clearable icon="o-magnifying-glass" />
        </x-slot:middle>
        <x-slot:actions>
            <x-button label="Sync" icon="o-arrow-path" wire:click="syncRepositories" spinner="syncRepositories" class="btn-primary" />
        </x-slot:actions>
    </x-header>

    <x-card shadow>
        <x-table :headers="$headers" :rows="$repositories" :sort-by="$sortBy" with-pagination>
            @scope('cell_value_tier', $repository)
                {{ $repository->value_tier?->getLabel() }}
            @endscope

            @scope('actions', $repository)
                <div class="flex gap-1">
                    <x-button icon="o-pencil" link="{{ route('workbench.repositories.edit', $repository->id) }}" spinner class="btn-ghost btn-sm" />
                    <x-button icon="o-trash" wire:click="delete({{ $repository->id }})" wire:confirm="Are you sure you want to delete this repository?" spinner class="btn-ghost btn-sm text-error" />
                </div>
            @endscope
        </x-table>
    </x-card>
</div>
