<div>
    <x-header title="AI Providers" separator />

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Claude --}}
        <x-card shadow>
            <x-header size="text-lg" separator class="mb-4">
                <x-slot:title>
                    <div class="flex items-center gap-2">
                        <div class="size-8 rounded-lg bg-orange-500/10 flex items-center justify-center">
                            <x-icon name="o-sparkles" class="size-4 text-orange-600" />
                        </div>
                        Claude
                    </div>
                </x-slot:title>
                <x-slot:subtitle>Anthropic Claude Code</x-slot:subtitle>
            </x-header>

            <div class="flex flex-col gap-4">
                <div class="text-sm">
                    <span class="font-medium">Status:</span>
                    <span class="{{ $claude?->is_active ? 'text-success' : 'text-base-content/50' }}">
                        {{ $claude?->is_active ? 'Active' : 'Inactive' }}
                    </span>
                </div>

                <x-input
                    label="Context Window (tokens)"
                    type="number"
                    min="0"
                    wire:model="claudeContextWindow"
                    placeholder="1000000"
                />

                <x-input
                    label="Monthly Quota Limit (tokens)"
                    type="number"
                    min="0"
                    wire:model="claudeQuotaLimit"
                    placeholder="No limit"
                />

                @if($claude && $claude->quota_limit)
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span>{{ number_format($claude->quota_used) }} tokens</span>
                            <span>{{ number_format($claude->getQuotaPercentage(), 1) }}%</span>
                        </div>
                        <progress class="progress progress-warning w-full h-2" value="{{ min(100, $claude->getQuotaPercentage()) }}" max="100"></progress>
                    </div>
                @endif

                <div class="flex gap-2 pt-2">
                    <x-button wire:click="saveClaude" class="btn-primary btn-sm">Save</x-button>
                    <x-button wire:click="resetQuota('claude')" wire:confirm="Reset Claude quota to zero?" class="btn-ghost btn-sm">Reset Quota</x-button>
                </div>
            </div>
        </x-card>

        {{-- Codex --}}
        <x-card shadow>
            <x-header size="text-lg" separator class="mb-4">
                <x-slot:title>
                    <div class="flex items-center gap-2">
                        <div class="size-8 rounded-lg bg-blue-500/10 flex items-center justify-center">
                            <x-icon name="o-bolt" class="size-4 text-blue-600" />
                        </div>
                        Codex
                    </div>
                </x-slot:title>
                <x-slot:subtitle>OpenAI Codex CLI</x-slot:subtitle>
            </x-header>

            <div class="flex flex-col gap-4">
                <div class="text-sm">
                    <span class="font-medium">Status:</span>
                    <span class="{{ $codex?->is_active ? 'text-success' : 'text-base-content/50' }}">
                        {{ $codex?->is_active ? 'Active' : 'Inactive' }}
                    </span>
                </div>

                <x-input
                    label="Model"
                    type="text"
                    wire:model="codexModel"
                    placeholder="o3 (default)"
                />

                <x-input
                    label="Context Window (tokens)"
                    type="number"
                    min="0"
                    wire:model="codexContextWindow"
                    placeholder="200000"
                />

                <x-input
                    label="Monthly Quota Limit (tokens)"
                    type="number"
                    min="0"
                    wire:model="codexQuotaLimit"
                    placeholder="No limit"
                />

                @if($codex && $codex->quota_limit)
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span>{{ number_format($codex->quota_used) }} tokens</span>
                            <span>{{ number_format($codex->getQuotaPercentage(), 1) }}%</span>
                        </div>
                        <progress class="progress progress-info w-full h-2" value="{{ min(100, $codex->getQuotaPercentage()) }}" max="100"></progress>
                    </div>
                @endif

                <div class="flex gap-2 pt-2">
                    <x-button wire:click="saveCodex" class="btn-primary btn-sm">Save</x-button>
                    <x-button wire:click="resetQuota('codex')" wire:confirm="Reset Codex quota to zero?" class="btn-ghost btn-sm">Reset Quota</x-button>
                </div>
            </div>
        </x-card>

        {{-- Kimi --}}
        <x-card shadow>
            <x-header size="text-lg" separator class="mb-4">
                <x-slot:title>
                    <div class="flex items-center gap-2">
                        <div class="size-8 rounded-lg bg-violet-500/10 flex items-center justify-center">
                            <x-icon name="o-sparkles" class="size-4 text-violet-600" />
                        </div>
                        Kimi
                    </div>
                </x-slot:title>
                <x-slot:subtitle>Kimi K2 (Moonshot AI)</x-slot:subtitle>
            </x-header>

            <div class="flex flex-col gap-4">
                <div class="text-sm">
                    <span class="font-medium">Status:</span>
                    <span class="{{ $kimi?->is_active ? 'text-success' : 'text-warning' }}">
                        {{ $kimi?->is_active ? 'Active' : 'Inactive (add API key)' }}
                    </span>
                </div>

                <x-input
                    label="API Key"
                    type="password"
                    wire:model="kimiApiKey"
                    placeholder="Enter Kimi API key"
                />

                <x-input
                    label="Model"
                    type="text"
                    wire:model="kimiModel"
                    placeholder="kimi-k2.5 (default)"
                    hint="Available: kimi-k2.5"
                />

                <x-input
                    label="Context Window (tokens)"
                    type="number"
                    min="0"
                    wire:model="kimiContextWindow"
                    placeholder="262144"
                />

                <x-input
                    label="Monthly Quota Limit (tokens)"
                    type="number"
                    min="0"
                    wire:model="kimiQuotaLimit"
                    placeholder="No limit"
                />

                @if($kimi && $kimi->quota_limit)
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span>{{ number_format($kimi->quota_used) }} tokens</span>
                            <span>{{ number_format($kimi->getQuotaPercentage(), 1) }}%</span>
                        </div>
                        <progress class="progress progress-secondary w-full h-2" value="{{ min(100, $kimi->getQuotaPercentage()) }}" max="100"></progress>
                        @if($kimi->quota_resets_at)
                            <div class="text-xs text-base-content/50 mt-1">Resets {{ $kimi->quota_resets_at->diffForHumans() }}</div>
                        @endif
                    </div>
                @endif

                <div class="flex gap-2 pt-2">
                    <x-button wire:click="saveKimi" class="btn-primary btn-sm">Save</x-button>
                    <x-button wire:click="resetQuota('kimi')" wire:confirm="Reset Kimi quota to zero?" class="btn-ghost btn-sm">Reset Quota</x-button>
                </div>
            </div>
        </x-card>
    </div>
</div>
