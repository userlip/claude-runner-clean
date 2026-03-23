<div>
    <x-header title="Scrapp APIs" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-input placeholder="Search..." wire:model.live.debounce="search" clearable icon="o-magnifying-glass" />
        </x-slot:middle>
        <x-slot:actions>
            <x-button label="Create" icon="o-plus" link="{{ route('workbench.scrapp-apis.create') }}" class="btn-primary" />
        </x-slot:actions>
    </x-header>

    <x-card shadow>
        <x-table :headers="$headers" :rows="$scrappApis" :sort-by="$sortBy" with-pagination>
            @scope('cell_is_active', $api)
                @if($api->is_active)
                    <x-badge value="Active" class="badge-success" />
                @else
                    <x-badge value="Inactive" class="badge-error" />
                @endif
            @endscope

            @scope('actions', $api)
                <div class="flex gap-1">
                    <x-button icon="o-pencil" link="{{ route('workbench.scrapp-apis.edit', $api->id) }}" spinner class="btn-ghost btn-sm" />
                    <x-button icon="o-trash" wire:click="delete({{ $api->id }})" wire:confirm="Are you sure you want to delete this API?" spinner class="btn-ghost btn-sm text-error" />
                </div>
            @endscope
        </x-table>
    </x-card>
</div>
