<div>
    <x-header title="Tasks" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-input placeholder="Search..." wire:model.live.debounce="search" clearable icon="o-magnifying-glass" />
        </x-slot:middle>
        <x-slot:actions>
            <x-toggle label="Security" wire:model.live="showSecurityTasks" class="toggle-sm" />
            <x-button label="Create" icon="o-plus" link="{{ route('workbench.tasks.create') }}" class="btn-primary" />
        </x-slot:actions>
    </x-header>

    <x-card shadow>
        <x-table :headers="$headers" :rows="$tasks" :sort-by="$sortBy" with-pagination link="/workbench/tasks/{uuid}">
            @scope('cell_last_message_at', $task)
                {{ $task->last_message_at?->diffForHumans() ?? '-' }}
            @endscope

            @scope('cell_created_at', $task)
                {{ $task->created_at?->diffForHumans() }}
            @endscope

            @scope('actions', $task)
                <div class="flex gap-1">
                    <x-button icon="o-chat-bubble-left-right" link="{{ route('workbench.tasks.show', $task->uuid) }}" spinner class="btn-ghost btn-sm" title="Open Chat" />
                    <x-button icon="o-pencil" link="{{ route('workbench.tasks.edit', $task->id) }}" spinner class="btn-ghost btn-sm" />
                    <x-button icon="o-trash" wire:click="delete({{ $task->id }})" wire:confirm="Are you sure you want to delete this task?" spinner class="btn-ghost btn-sm text-error" />
                </div>
            @endscope
        </x-table>
    </x-card>
</div>
