<div>
    <x-header title="Promotion Directories" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-input placeholder="Search..." wire:model.live.debounce="search" clearable icon="o-magnifying-glass" />
        </x-slot:middle>
        <x-slot:actions>
            <x-button label="Create" icon="o-plus" link="{{ route('workbench.promotion-directories.create') }}" class="btn-primary" />
        </x-slot:actions>
    </x-header>

    <x-card shadow>
        <x-table :headers="$headers" :rows="$directories" :sort-by="$sortBy" with-pagination>
            @scope('cell_category', $directory)
                {{ $directory->category->label() }}
            @endscope

            @scope('actions', $directory)
                <div class="flex gap-1">
                    <x-button icon="o-pencil" link="{{ route('workbench.promotion-directories.edit', $directory->uuid) }}" spinner class="btn-ghost btn-sm" />
                    <x-button icon="o-trash" wire:click="delete({{ $directory->id }})" wire:confirm="Are you sure you want to delete this directory?" spinner class="btn-ghost btn-sm text-error" />
                </div>
            @endscope
        </x-table>
    </x-card>
</div>
