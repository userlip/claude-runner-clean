<div>
    <x-header :title="$snippet ? 'Edit Snippet: ' . $snippet->name : 'Create Snippet'" separator />

    <x-card shadow>
        <x-form wire:submit="save">
            <x-input label="Name" wire:model="name" placeholder="Snippet name" required />
            <x-textarea label="Content" wire:model="content" rows="5" required />
            <x-input label="Sort Order" wire:model="sortOrder" type="number" min="0" />

            <x-slot:actions>
                <x-button label="Cancel" link="{{ route('workbench.snippets.index') }}" />
                <x-button label="{{ $snippet ? 'Update' : 'Create' }}" type="submit" icon="o-paper-airplane" class="btn-primary" spinner="save" />
            </x-slot:actions>
        </x-form>
    </x-card>
</div>
