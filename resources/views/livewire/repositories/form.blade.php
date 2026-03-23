<div>
    <x-header :title="$repository ? 'Edit Repository: ' . $repository->name : 'Create Repository'" separator />

    <x-card shadow>
        <x-form wire:submit="save">
            <x-input label="Name" wire:model="name" placeholder="my-repo" required />
            <x-input label="Full Name" wire:model="fullName" placeholder="owner/my-repo" />
            <x-textarea label="Description" wire:model="description" rows="3" />
            <x-input label="Default Branch" wire:model="defaultBranch" placeholder="main" />
            <x-select label="Value Tier" wire:model="valueTier" :options="$valueTierOptions" placeholder="Select value tier" placeholder-value="" />
            <x-checkbox label="Private" wire:model="isPrivate" />

            <x-slot:actions>
                <x-button label="Cancel" link="{{ route('workbench.repositories.index') }}" />
                <x-button label="{{ $repository ? 'Update' : 'Create' }}" type="submit" icon="o-paper-airplane" class="btn-primary" spinner="save" />
            </x-slot:actions>
        </x-form>
    </x-card>
</div>
