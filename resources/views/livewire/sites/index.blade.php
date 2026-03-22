<div>
    <x-header title="Sites" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-input placeholder="Search..." wire:model.live.debounce="search" clearable icon="o-magnifying-glass" />
        </x-slot:middle>
        <x-slot:actions>
            <x-button label="Create" icon="o-plus" link="{{ route('app.sites.create') }}" class="btn-primary" />
        </x-slot:actions>
    </x-header>

    <x-card shadow>
        <x-table :headers="$headers" :rows="$sites" :sort-by="$sortBy" with-pagination>
            @scope('cell_status', $site)
                {{ $site->status?->label() }}
            @endscope

            @scope('actions', $site)
                <x-button icon="o-pencil" link="{{ route('app.sites.edit', $site->id) }}" spinner class="btn-ghost btn-sm" />
                <x-button icon="o-trash" wire:click="delete({{ $site->id }})" wire:confirm="Are you sure you want to delete this site?" spinner class="btn-ghost btn-sm text-error" />
            @endscope
        </x-table>
    </x-card>
</div>
