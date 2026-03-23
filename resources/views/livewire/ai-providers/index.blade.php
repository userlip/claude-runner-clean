<div>
    <x-header title="AI Providers" separator>
        <x-slot:actions>
            <x-button
                label="{{ $showAddForm ? 'Cancel' : 'Add Provider' }}"
                icon="{{ $showAddForm ? 'o-x-mark' : 'o-plus' }}"
                wire:click="$toggle('showAddForm')"
                class="{{ $showAddForm ? 'btn-ghost' : 'btn-primary' }} btn-sm"
            />
        </x-slot:actions>
    </x-header>

    {{-- Add New Provider Form --}}
    @if($showAddForm)
        <x-card shadow class="mb-6">
            <x-header title="New Provider" subtitle="Add any Claude-compatible AI provider (Anthropic API format)." size="text-lg" class="mb-4" separator />

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-input label="Display Name" wire:model="newDisplayName" placeholder="e.g. DeepSeek" required />
                <x-input label="Model" wire:model="newModel" placeholder="e.g. deepseek-chat" required />
                <x-input label="Base URL" wire:model="newBaseUrl" placeholder="https://api.deepseek.com" required />
                <x-input label="API Key" type="password" wire:model="newApiKey" placeholder="sk-..." required />
                <x-input label="Context Window (tokens)" type="number" wire:model="newContextWindow" placeholder="200000" />
            </div>

            <div class="flex gap-2 pt-4">
                <x-button label="Add Provider" wire:click="addProvider" spinner="addProvider" class="btn-primary btn-sm" />
            </div>
        </x-card>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        @foreach($providers as $provider)
            @php
                $id = $provider->id;
                $isBuiltin = $provider->isClaude() || $provider->isCodex();
            @endphp
            <x-card shadow>
                <x-header size="text-lg" separator class="mb-4">
                    <x-slot:title>
                        <div class="flex items-center gap-2">
                            <div class="size-8 rounded-lg bg-primary/10 flex items-center justify-center">
                                <x-icon name="o-cpu-chip" class="size-4 text-primary" />
                            </div>
                            <div>
                                {{ $provider->display_name }}
                                @if($provider->is_default)
                                    <span class="badge badge-xs badge-primary ml-1">Default</span>
                                @endif
                            </div>
                        </div>
                    </x-slot:title>
                    <x-slot:subtitle>
                        {{ $isBuiltin ? 'Built-in' : ($provider->base_url ?? 'Custom provider') }}
                    </x-slot:subtitle>
                </x-header>

                <div class="flex flex-col gap-4">
                    {{-- Status --}}
                    <div class="flex items-center justify-between">
                        <div class="text-sm">
                            <span class="font-medium">Status:</span>
                            <span class="{{ $provider->is_active ? 'text-success' : 'text-base-content/50' }}">
                                {{ $provider->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                        <x-toggle wire:model="provider_{{ $id }}_is_active" />
                    </div>

                    <x-input label="Display Name" wire:model="provider_{{ $id }}_display_name" />

                    {{-- Connection fields (only for non-builtin) --}}
                    @if(!$isBuiltin)
                        <x-input label="Base URL" wire:model="provider_{{ $id }}_base_url" placeholder="https://api.example.com" />
                        <x-input label="API Key" type="password" wire:model="provider_{{ $id }}_api_key" placeholder="Leave blank to keep current" />
                    @endif

                    <x-input label="Model" wire:model="provider_{{ $id }}_model" placeholder="{{ $isBuiltin ? 'Uses system default' : 'Model identifier' }}" />
                    <x-input label="Context Window (tokens)" type="number" wire:model="provider_{{ $id }}_context_window" placeholder="200000" />
                    <x-input label="Quota Limit (tokens)" type="number" wire:model="provider_{{ $id }}_quota_limit" placeholder="No limit" />

                    {{-- Quota progress --}}
                    @if($provider->quota_limit)
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span>{{ number_format($provider->quota_used) }} tokens</span>
                                <span>{{ number_format($provider->getQuotaPercentage(), 1) }}%</span>
                            </div>
                            <progress class="progress progress-primary w-full h-2" value="{{ min(100, $provider->getQuotaPercentage()) }}" max="100"></progress>
                            @if($provider->quota_resets_at)
                                <div class="text-xs text-base-content/50 mt-1">Resets {{ $provider->quota_resets_at->diffForHumans() }}</div>
                            @endif
                        </div>
                    @endif

                    {{-- Actions --}}
                    <div class="flex flex-wrap gap-2 pt-2">
                        <x-button label="Save" wire:click="saveProvider({{ $id }})" spinner="saveProvider({{ $id }})" class="btn-primary btn-sm" />
                        @if(!$provider->is_default)
                            <x-button label="Set Default" wire:click="setDefault({{ $id }})" spinner="setDefault({{ $id }})" class="btn-ghost btn-sm" />
                        @endif
                        <x-button label="Reset Quota" wire:click="resetQuota({{ $id }})" wire:confirm="Reset {{ $provider->display_name }} quota to zero?" class="btn-ghost btn-sm" />
                        @if(!$isBuiltin)
                            <x-button label="Delete" wire:click="deleteProvider({{ $id }})" wire:confirm="Delete {{ $provider->display_name }}? This cannot be undone." class="btn-ghost btn-sm text-error" />
                        @endif
                    </div>
                </div>
            </x-card>
        @endforeach
    </div>
</div>
