<div>
    <x-header :title="$site ? 'Edit Site: ' . $site->domain : 'Create Site'" separator />

    <x-card shadow>
        <x-form wire:submit="save">
            <x-input label="Domain" wire:model="domain" placeholder="example.marin.sh" required />
            <x-select label="Repository" wire:model="repositoryId" :options="$repositoryOptions" placeholder="Select repository" placeholder-value="" />
            <x-input label="Branch" wire:model="branch" placeholder="main" />
            <x-input label="PHP Version" wire:model="phpVersion" placeholder="8.4" />
            <x-input label="Web Directory" wire:model="webDirectory" placeholder="/public" />
            <x-select label="Status" wire:model="status" :options="$statusOptions" required />

            <x-slot:actions>
                <x-button label="Cancel" link="{{ route('workbench.sites.index') }}" />
                <x-button label="{{ $site ? 'Update' : 'Create' }}" type="submit" icon="o-paper-airplane" class="btn-primary" spinner="save" />
            </x-slot:actions>
        </x-form>
    </x-card>
</div>
