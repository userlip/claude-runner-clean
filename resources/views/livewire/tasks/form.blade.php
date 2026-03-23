<div>
    <x-header :title="$task ? 'Edit Task: ' . $task->title : 'New Chat'" separator />

    <x-card shadow>
        <x-form wire:submit="save">
            @if($task)
                {{-- Edit mode: show all fields --}}
                <x-input label="Title" wire:model="title" placeholder="Task title" required />
                <x-select label="Status" wire:model="status" :options="$statusOptions" required />
                <x-choices-offline label="Repository" wire:model="repositoryId" :options="$repositoryOptions" placeholder="Search repository..." single />
                <x-choices-offline label="Site" wire:model="siteId" :options="$siteOptions" placeholder="Search site..." single />
                <x-choices-offline label="AI Provider" wire:model="aiProviderId" :options="$aiProviderOptions" placeholder="Search AI provider..." single />
            @else
                {{-- Create mode: simple repo + location --}}
                <x-choices-offline
                    label="Repository"
                    wire:model.live="repositoryId"
                    :options="$repositoryOptions"
                    placeholder="Search for a repository..."
                    searchable
                    single
                    required
                />

                @if($repositoryId)
                    <x-radio
                        label="Work Location"
                        wire:model="workLocation"
                        :options="$workLocationOptions"
                    />
                @endif
            @endif

            <x-slot:actions>
                <x-button label="Cancel" link="{{ route('workbench.tasks.index') }}" />
                <x-button label="{{ $task ? 'Update' : 'Start Chat' }}" type="submit" icon="o-paper-airplane" class="btn-primary" spinner="save" />
            </x-slot:actions>
        </x-form>
    </x-card>
</div>
