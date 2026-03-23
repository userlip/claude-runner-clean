<div>
    <x-header :title="$directory ? 'Edit Directory: ' . $directory->name : 'Create Promotion Directory'" separator />

    <x-card shadow>
        <x-form wire:submit="save">
            <x-input label="Name" wire:model="name" placeholder="Directory name" required />
            <x-input label="URL" wire:model="url" placeholder="https://example.com" required />
            <x-select label="Category" wire:model="category" :options="$categoryOptions" required />
            <x-select label="Submission Type" wire:model="submission_type" :options="$submissionTypeOptions" required />
            <x-input label="Submission URL" wire:model="submission_url" placeholder="https://example.com/submit" />
            <x-textarea label="Notes" wire:model="notes" rows="3" />

            <x-slot:actions>
                <x-button label="Cancel" link="{{ route('workbench.promotion-directories.index') }}" />
                <x-button label="{{ $directory ? 'Update' : 'Create' }}" type="submit" icon="o-paper-airplane" class="btn-primary" spinner="save" />
            </x-slot:actions>
        </x-form>
    </x-card>
</div>
