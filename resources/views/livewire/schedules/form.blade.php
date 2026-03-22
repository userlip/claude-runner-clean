<div>
    <x-header :title="$schedule ? 'Edit Schedule: ' . $schedule->name : 'Create Schedule'" separator />

    <x-card shadow>
        <x-form wire:submit="save">
            <x-input label="Name" wire:model="name" placeholder="Schedule name" required />
            <x-textarea label="Prompt" wire:model="prompt" placeholder="Task prompt..." rows="4" required />
            <x-input label="Cron Expression" wire:model="cronExpression" placeholder="0 * * * *" required />
            <x-toggle label="Active" wire:model="isActive" />
            <x-select label="Repository" wire:model="repositoryId" :options="$repositoryOptions" required />
            <x-select label="AI Provider" wire:model="aiProviderId" :options="$aiProviderOptions" placeholder="Select AI provider" placeholder-value="" />
            <x-select label="Persona" wire:model="personaId" :options="$personaOptions" placeholder="Select persona" placeholder-value="" />
            <x-input label="Delete After (minutes)" wire:model="deleteAfterMinutes" type="number" placeholder="Leave blank to keep forever" />

            <x-slot:actions>
                <x-button label="Cancel" link="{{ route('app.schedules.index') }}" />
                <x-button label="{{ $schedule ? 'Update' : 'Create' }}" type="submit" icon="o-paper-airplane" class="btn-primary" spinner="save" />
            </x-slot:actions>
        </x-form>
    </x-card>
</div>
