<div>
    <x-header :title="$proposal ? 'Edit Proposal: ' . $proposal->title : 'Create Proposal'" separator />

    <x-card shadow>
        <x-form wire:submit="save">
            <x-input label="Title" wire:model="title" placeholder="Proposal title" required />
            <x-textarea label="Description" wire:model="description" rows="3" />
            <x-input label="Project" wire:model="project" placeholder="project-slug" />
            <x-select label="Priority" wire:model="priority" :options="$priorityOptions" required />
            <x-select label="Status" wire:model="status" :options="$statusOptions" required />
            <x-select label="Type" wire:model="type" :options="$typeOptions" required />

            <x-slot:actions>
                <x-button label="Cancel" link="{{ route('workbench.proposals.index') }}" />
                <x-button label="{{ $proposal ? 'Update' : 'Create' }}" type="submit" icon="o-paper-airplane" class="btn-primary" spinner="save" />
            </x-slot:actions>
        </x-form>
    </x-card>
</div>
