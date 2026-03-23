<div>
    <x-header :title="$persona ? 'Edit Persona: ' . $persona->name : 'Create Persona'" separator />

    <x-card shadow>
        <x-form wire:submit="save">
            <x-input label="Name" wire:model="name" placeholder="Persona name" required />
            <x-select label="Repository" wire:model="repository_id" :options="$repositoryOptions" required />
            <x-textarea label="Description" wire:model="description" rows="2" />
            <x-textarea label="Master Prompt" wire:model="master_prompt" rows="6" />
            <x-textarea label="MCP Guidance" wire:model="mcp_guidance" rows="3" />
            <x-select label="Status" wire:model="status" :options="$statusOptions" required />
            <x-checkbox label="Active" wire:model="is_active" />

            <x-slot:actions>
                <x-button label="Cancel" link="{{ route('workbench.personas.index') }}" />
                <x-button label="{{ $persona ? 'Update' : 'Create' }}" type="submit" icon="o-paper-airplane" class="btn-primary" spinner="save" />
            </x-slot:actions>
        </x-form>
    </x-card>

    @if ($persona && count($personaProposals) > 0)
        <div class="mt-6">
            <h3 class="text-lg font-semibold mb-3">Proposals</h3>
            <x-card shadow>
                <table class="table w-full">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($personaProposals as $proposal)
                            <tr wire:key="proposal-{{ $proposal->id }}">
                                <td>{{ $proposal->title }}</td>
                                <td>{{ $proposal->type->label() }}</td>
                                <td>{{ $proposal->status->label() }}</td>
                                <td>{{ $proposal->created_at->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-card>
        </div>
    @elseif ($persona)
        <div class="mt-6">
            <h3 class="text-lg font-semibold mb-3">Proposals</h3>
            <x-card shadow>
                <p class="text-gray-500">No proposals yet.</p>
            </x-card>
        </div>
    @endif
</div>
