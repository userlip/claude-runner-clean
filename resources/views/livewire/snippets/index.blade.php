<div>
    <x-header title="Snippets" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-input placeholder="Search..." wire:model.live.debounce="search" clearable icon="o-magnifying-glass" />
        </x-slot:middle>
        <x-slot:actions>
            <x-button label="Create" icon="o-plus" link="{{ route('app.snippets.create') }}" class="btn-primary" />
        </x-slot:actions>
    </x-header>

    <x-card shadow>
        <x-table :headers="$headers" :rows="$snippets" :sort-by="$sortBy" with-pagination>
            @scope('actions', $snippet)
                <x-button icon="o-pencil" link="{{ route('app.snippets.edit', $snippet->id) }}" spinner class="btn-ghost btn-sm" />
                <x-button icon="o-trash" wire:click="delete({{ $snippet->id }})" wire:confirm="Are you sure you want to delete this snippet?" spinner class="btn-ghost btn-sm text-error" />
            @endscope
        </x-table>
    </x-card>
</div>
