<div>
    <x-header title="Users" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-input placeholder="Search..." wire:model.live.debounce="search" clearable icon="o-magnifying-glass" />
        </x-slot:middle>
        <x-slot:actions>
            <x-button label="Create" icon="o-plus" link="{{ route('app.users.create') }}" class="btn-primary" />
        </x-slot:actions>
    </x-header>

    <x-card shadow>
        <x-table :headers="$headers" :rows="$users" :sort-by="$sortBy" with-pagination>
            @scope('cell_created_at', $user)
                {{ $user->created_at?->diffForHumans() }}
            @endscope

            @scope('actions', $user)
                <x-button icon="o-pencil" link="{{ route('app.users.edit', $user->id) }}" spinner class="btn-ghost btn-sm" />
                <x-button icon="o-trash" wire:click="delete({{ $user->id }})" wire:confirm="Are you sure you want to delete this user?" spinner class="btn-ghost btn-sm text-error" />
            @endscope
        </x-table>
    </x-card>
</div>
