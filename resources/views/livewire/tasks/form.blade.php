<div>
    <x-header :title="$task ? 'Edit Task: ' . $task->title : 'Create Task'" separator />

    <x-card shadow>
        <x-form wire:submit="save">
            <x-input label="Title" wire:model="title" placeholder="Task title" required />
            <x-select label="Status" wire:model="status" :options="$statusOptions" required />
            <x-select label="Repository" wire:model="repositoryId" :options="$repositoryOptions" placeholder="Select repository" placeholder-value="" />
            <x-select label="Site" wire:model="siteId" :options="$siteOptions" placeholder="Select site" placeholder-value="" />
            <x-select label="AI Provider" wire:model="aiProviderId" :options="$aiProviderOptions" placeholder="Select AI provider" placeholder-value="" />

            <x-slot:actions>
                <x-button label="Cancel" link="{{ route('app.tasks.index') }}" />
                <x-button label="{{ $task ? 'Update' : 'Create' }}" type="submit" icon="o-paper-airplane" class="btn-primary" spinner="save" />
            </x-slot:actions>
        </x-form>
    </x-card>
</div>
