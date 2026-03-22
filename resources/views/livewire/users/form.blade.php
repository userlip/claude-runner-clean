<div>
    <x-header :title="$user ? 'Edit User: ' . $user->name : 'Create User'" separator />

    <x-card shadow>
        <x-form wire:submit="save">
            <x-input label="Name" wire:model="name" placeholder="Full name" required />
            <x-input label="Email" wire:model="email" type="email" placeholder="email@example.com" required />
            <x-input label="{{ $user ? 'New Password' : 'Password' }}" wire:model="password" type="password" placeholder="{{ $user ? 'Leave blank to keep current password' : 'Password' }}" :required="!$user" />
            <x-input label="Confirm Password" wire:model="passwordConfirmation" type="password" placeholder="Confirm password" :required="!$user" />

            <x-slot:actions>
                <x-button label="Cancel" link="{{ route('app.users.index') }}" />
                <x-button label="{{ $user ? 'Update' : 'Create' }}" type="submit" icon="o-paper-airplane" class="btn-primary" spinner="save" />
            </x-slot:actions>
        </x-form>
    </x-card>
</div>
