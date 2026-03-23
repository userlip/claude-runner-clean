<div>
    <x-header :title="$playbook ? 'Edit Playbook: ' . $playbook->name : 'Create Playbook'" separator />

    <x-card shadow>
        <x-form wire:submit="save">
            <x-input label="Name" wire:model="name" placeholder="Playbook name" required />
            <x-select label="Proposal Type" wire:model="proposal_type" :options="$proposalTypeOptions" required />
            <x-input label="Project" wire:model="project" placeholder="project-slug (leave blank for generic)" />
            <x-textarea label="Description" wire:model="description" rows="2" />
            <x-textarea label="Prompt Template" wire:model="prompt_template" rows="8" />
            <x-checkbox label="Active" wire:model="is_active" />

            <x-slot:actions>
                <x-button label="Cancel" link="{{ route('workbench.playbooks.index') }}" />
                <x-button label="{{ $playbook ? 'Update' : 'Create' }}" type="submit" icon="o-paper-airplane" class="btn-primary" spinner="save" />
            </x-slot:actions>
        </x-form>
    </x-card>
</div>
