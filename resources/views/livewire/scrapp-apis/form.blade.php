<div>
    <x-header :title="$scrappApi ? 'Edit API: ' . $scrappApi->name : 'Create Scrapp API'" separator />

    <x-card shadow>
        <x-form wire:submit="save">
            <x-input label="Name" wire:model="name" placeholder="My API" required />
            <x-input label="Slug" wire:model="slug" placeholder="my-api" required />
            <x-input label="Route Prefix" wire:model="route_prefix" placeholder="api/my-api" />
            <x-input label="RapidAPI Slug" wire:model="rapidapi_slug" placeholder="my-api-scraper" />
            <x-toggle label="Active" wire:model="is_active" />
            <x-textarea label="Notes" wire:model="notes" rows="3" />

            <x-slot:actions>
                <x-button label="Cancel" link="{{ route('app.scrapp-apis.index') }}" />
                <x-button label="{{ $scrappApi ? 'Update' : 'Create' }}" type="submit" icon="o-paper-airplane" class="btn-primary" spinner="save" />
            </x-slot:actions>
        </x-form>
    </x-card>
</div>
