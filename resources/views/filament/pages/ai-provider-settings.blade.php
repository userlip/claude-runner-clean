<x-filament-panels::page>
    <div style="display: grid; gap: 1.5rem; grid-template-columns: repeat(3, minmax(0, 1fr));">
        {{-- Claude Settings --}}
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg" style="background-color: rgba(251, 146, 60, 0.1);">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem; color: rgb(234, 88, 12);">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456ZM16.894 20.567 16.5 21.75l-.394-1.183a2.25 2.25 0 0 0-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 0 0 1.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 0 0 1.423 1.423l1.183.394-1.183.394a2.25 2.25 0 0 0-1.423 1.423Z" />
                        </svg>
                    </div>
                    <div>
                        <span>Claude</span>
                        <p style="font-size: 0.75rem; font-weight: normal; color: rgb(107, 114, 128); margin: 0;">Anthropic Claude Code</p>
                    </div>
                </div>
            </x-slot>

            @php $claude = $this->getClaudeProvider(); @endphp

            <div style="display: flex; flex-direction: column; gap: 1rem;">
                <div>
                    <label style="font-size: 0.875rem; font-weight: 500;">Status</label>
                    <p style="margin-top: 0.25rem; font-size: 0.875rem; color: {{ $claude?->is_active ? 'rgb(22, 163, 74)' : 'rgb(107, 114, 128)' }};">
                        {{ $claude?->is_active ? 'Active (using default credentials)' : 'Inactive' }}
                    </p>
                </div>

                <div>
                    <label style="font-size: 0.875rem; font-weight: 500;">Monthly Quota Limit (tokens)</label>
                    <input
                        type="number"
                        min="0"
                        wire:model="claudeQuotaLimit"
                        style="margin-top: 0.25rem; display: block; width: 100%; border-radius: 0.5rem; border: 1px solid rgb(209, 213, 219); padding: 0.5rem 0.75rem; font-size: 0.875rem;"
                        placeholder="10000000"
                    />
                </div>

                <div>
                    <label style="font-size: 0.875rem; font-weight: 500;">Context Window (tokens)</label>
                    <input
                        type="number"
                        min="0"
                        wire:model="claudeContextWindow"
                        style="margin-top: 0.25rem; display: block; width: 100%; border-radius: 0.5rem; border: 1px solid rgb(209, 213, 219); padding: 0.5rem 0.75rem; font-size: 0.875rem;"
                        placeholder="200000 (default)"
                    />
                </div>

                @if($claude)
                    <div>
                        <label style="font-size: 0.875rem; font-weight: 500;">Current Usage</label>
                        <div style="margin-top: 0.5rem;">
                            <div style="display: flex; justify-content: space-between; font-size: 0.875rem; margin-bottom: 0.25rem;">
                                <span>{{ number_format($claude->quota_used) }} tokens</span>
                                <span>{{ number_format($claude->getQuotaPercentage(), 1) }}%</span>
                            </div>
                            <div style="height: 0.5rem; width: 100%; border-radius: 9999px; background-color: rgb(229, 231, 235);">
                                <div
                                    style="height: 0.5rem; border-radius: 9999px; background-color: rgb(249, 115, 22); width: {{ min(100, $claude->getQuotaPercentage()) }}%;"
                                ></div>
                            </div>
                        </div>
                    </div>
                @endif

                <div style="display: flex; gap: 0.5rem; padding-top: 0.5rem;">
                    <x-filament::button wire:click="saveClaudeSettings">
                        Save
                    </x-filament::button>
                    <x-filament::button color="gray" wire:click="resetQuota('claude')" wire:confirm="Reset Claude quota to zero?">
                        Reset Quota
                    </x-filament::button>
                </div>
            </div>
        </x-filament::section>

        {{-- Codex Settings --}}
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg" style="background-color: rgba(59, 130, 246, 0.1);">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem; color: rgb(37, 99, 235);">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m3.75 13.5 10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z" />
                        </svg>
                    </div>
                    <div>
                        <span>Codex</span>
                        <p style="font-size: 0.75rem; font-weight: normal; color: rgb(107, 114, 128); margin: 0;">OpenAI Codex CLI</p>
                    </div>
                </div>
            </x-slot>

            @php $codex = $this->getCodexProvider(); @endphp

            <div style="display: flex; flex-direction: column; gap: 1rem;">
                <div>
                    <label style="font-size: 0.875rem; font-weight: 500;">Status</label>
                    <p style="margin-top: 0.25rem; font-size: 0.875rem; color: {{ $codex?->is_active ? 'rgb(22, 163, 74)' : 'rgb(107, 114, 128)' }};">
                        {{ $codex?->is_active ? 'Active (using local Codex CLI)' : 'Inactive' }}
                    </p>
                </div>

                <div>
                    <label style="font-size: 0.875rem; font-weight: 500;">Model</label>
                    <input
                        type="text"
                        wire:model="codexModel"
                        style="margin-top: 0.25rem; display: block; width: 100%; border-radius: 0.5rem; border: 1px solid rgb(209, 213, 219); padding: 0.5rem 0.75rem; font-size: 0.875rem;"
                        placeholder="o3 (default)"
                    />
                </div>

                <div>
                    <label style="font-size: 0.875rem; font-weight: 500;">Monthly Quota Limit (tokens)</label>
                    <input
                        type="number"
                        min="0"
                        wire:model="codexQuotaLimit"
                        style="margin-top: 0.25rem; display: block; width: 100%; border-radius: 0.5rem; border: 1px solid rgb(209, 213, 219); padding: 0.5rem 0.75rem; font-size: 0.875rem;"
                        placeholder="10000000"
                    />
                </div>

                <div>
                    <label style="font-size: 0.875rem; font-weight: 500;">Context Window (tokens)</label>
                    <input
                        type="number"
                        min="0"
                        wire:model="codexContextWindow"
                        style="margin-top: 0.25rem; display: block; width: 100%; border-radius: 0.5rem; border: 1px solid rgb(209, 213, 219); padding: 0.5rem 0.75rem; font-size: 0.875rem;"
                        placeholder="200000 (default)"
                    />
                </div>

                @if($codex)
                    <div>
                        <label style="font-size: 0.875rem; font-weight: 500;">Current Usage</label>
                        <div style="margin-top: 0.5rem;">
                            <div style="display: flex; justify-content: space-between; font-size: 0.875rem; margin-bottom: 0.25rem;">
                                <span>{{ number_format($codex->quota_used) }} tokens</span>
                                <span>{{ number_format($codex->getQuotaPercentage(), 1) }}%</span>
                            </div>
                            <div style="height: 0.5rem; width: 100%; border-radius: 9999px; background-color: rgb(229, 231, 235);">
                                <div
                                    style="height: 0.5rem; border-radius: 9999px; background-color: rgb(59, 130, 246); width: {{ min(100, $codex->getQuotaPercentage()) }}%;"
                                ></div>
                            </div>
                        </div>
                    </div>
                @endif

                <div style="display: flex; gap: 0.5rem; padding-top: 0.5rem;">
                    <x-filament::button wire:click="saveCodexSettings">
                        Save
                    </x-filament::button>
                    <x-filament::button color="gray" wire:click="resetQuota('codex')" wire:confirm="Reset Codex quota to zero?">
                        Reset Quota
                    </x-filament::button>
                </div>
            </div>
        </x-filament::section>

        {{-- GLM Settings --}}
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg" style="background-color: rgba(59, 130, 246, 0.1);">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem; color: rgb(37, 99, 235);">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m3.75 13.5 10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z" />
                        </svg>
                    </div>
                    <div>
                        <span>GLM (z.ai)</span>
                        <p style="font-size: 0.75rem; font-weight: normal; color: rgb(107, 114, 128); margin: 0;">GLM-5 via z.ai</p>
                    </div>
                </div>
            </x-slot>

            @php $glm = $this->getGlmProvider(); @endphp

            <div style="display: flex; flex-direction: column; gap: 1rem;">
                <div>
                    <label style="font-size: 0.875rem; font-weight: 500;">Status</label>
                    <p style="margin-top: 0.25rem; font-size: 0.875rem; color: {{ $glm?->is_active ? 'rgb(22, 163, 74)' : 'rgb(202, 138, 4)' }};">
                        {{ $glm?->is_active ? 'Active' : 'Inactive (add API key to enable)' }}
                    </p>
                </div>

                <div>
                    <label style="font-size: 0.875rem; font-weight: 500;">API Key</label>
                    <input
                        type="password"
                        wire:model="glmApiKey"
                        style="margin-top: 0.25rem; display: block; width: 100%; border-radius: 0.5rem; border: 1px solid rgb(209, 213, 219); padding: 0.5rem 0.75rem; font-size: 0.875rem;"
                        placeholder="Enter z.ai API key"
                    />
                </div>

                <div>
                    <label style="font-size: 0.875rem; font-weight: 500;">Quota Limit (tokens per 5-hour cycle)</label>
                    <input
                        type="number"
                        min="0"
                        wire:model="glmQuotaLimit"
                        style="margin-top: 0.25rem; display: block; width: 100%; border-radius: 0.5rem; border: 1px solid rgb(209, 213, 219); padding: 0.5rem 0.75rem; font-size: 0.875rem;"
                        placeholder="50000000"
                    />
                </div>

                <div>
                    <label style="font-size: 0.875rem; font-weight: 500;">Context Window (tokens)</label>
                    <input
                        type="number"
                        min="0"
                        wire:model="glmContextWindow"
                        style="margin-top: 0.25rem; display: block; width: 100%; border-radius: 0.5rem; border: 1px solid rgb(209, 213, 219); padding: 0.5rem 0.75rem; font-size: 0.875rem;"
                        placeholder="200000 (default)"
                    />
                </div>

                @if($glm)
                    <div>
                        <label style="font-size: 0.875rem; font-weight: 500;">Current Usage</label>
                        <div style="margin-top: 0.5rem;">
                            <div style="display: flex; justify-content: space-between; font-size: 0.875rem; margin-bottom: 0.25rem;">
                                <span>{{ number_format($glm->quota_used) }} tokens</span>
                                <span>{{ number_format($glm->getQuotaPercentage(), 1) }}%</span>
                            </div>
                            <div style="height: 0.5rem; width: 100%; border-radius: 9999px; background-color: rgb(229, 231, 235);">
                                <div
                                    style="height: 0.5rem; border-radius: 9999px; background-color: rgb(59, 130, 246); width: {{ min(100, $glm->getQuotaPercentage()) }}%;"
                                ></div>
                            </div>
                            @if($glm->quota_resets_at)
                                <p style="font-size: 0.75rem; color: rgb(107, 114, 128); margin-top: 0.25rem;">
                                    Resets {{ $glm->quota_resets_at->diffForHumans() }}
                                </p>
                            @endif
                        </div>
                    </div>
                @endif

                <div style="display: flex; gap: 0.5rem; padding-top: 0.5rem;">
                    <x-filament::button wire:click="saveGlmSettings">
                        Save
                    </x-filament::button>
                    <x-filament::button color="gray" wire:click="resetQuota('glm')" wire:confirm="Reset GLM quota to zero?">
                        Reset Quota
                    </x-filament::button>
                </div>
            </div>
        </x-filament::section>

        {{-- Minimax Settings --}}
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg" style="background-color: rgba(16, 185, 129, 0.1);">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem; color: rgb(5, 150, 105);">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0 1 18 16.5h-2.25m-7.5 0h7.5m-7.5 0-1 3m8.5-3 1 3m0 0 .5 1.5m-.5-1.5h-9.5m0 0-.5 1.5m.75-9 3-3 2.148 2.148A12.061 12.061 0 0 1 16.5 7.605" />
                        </svg>
                    </div>
                    <div>
                        <span>Minimax</span>
                        <p style="font-size: 0.75rem; font-weight: normal; color: rgb(107, 114, 128); margin: 0;">MiniMax-M2.5</p>
                    </div>
                </div>
            </x-slot>

            @php $minimax = $this->getMinimaxProvider(); @endphp

            <div style="display: flex; flex-direction: column; gap: 1rem;">
                <div>
                    <label style="font-size: 0.875rem; font-weight: 500;">Status</label>
                    <p style="margin-top: 0.25rem; font-size: 0.875rem; color: {{ $minimax?->is_active ? 'rgb(22, 163, 74)' : 'rgb(202, 138, 4)' }};">
                        {{ $minimax?->is_active ? 'Active' : 'Inactive (add API key to enable)' }}
                    </p>
                </div>

                <div>
                    <label style="font-size: 0.875rem; font-weight: 500;">API Key</label>
                    <input
                        type="password"
                        wire:model="minimaxApiKey"
                        style="margin-top: 0.25rem; display: block; width: 100%; border-radius: 0.5rem; border: 1px solid rgb(209, 213, 219); padding: 0.5rem 0.75rem; font-size: 0.875rem;"
                        placeholder="Enter Minimax API key"
                    />
                </div>

                <div>
                    <label style="font-size: 0.875rem; font-weight: 500;">Monthly Quota Limit (tokens)</label>
                    <input
                        type="number"
                        min="0"
                        wire:model="minimaxQuotaLimit"
                        style="margin-top: 0.25rem; display: block; width: 100%; border-radius: 0.5rem; border: 1px solid rgb(209, 213, 219); padding: 0.5rem 0.75rem; font-size: 0.875rem;"
                        placeholder="50000000"
                    />
                </div>

                <div>
                    <label style="font-size: 0.875rem; font-weight: 500;">Context Window (tokens)</label>
                    <input
                        type="number"
                        min="0"
                        wire:model="minimaxContextWindow"
                        style="margin-top: 0.25rem; display: block; width: 100%; border-radius: 0.5rem; border: 1px solid rgb(209, 213, 219); padding: 0.5rem 0.75rem; font-size: 0.875rem;"
                        placeholder="200000 (default)"
                    />
                </div>

                @if($minimax)
                    <div>
                        <label style="font-size: 0.875rem; font-weight: 500;">Current Usage</label>
                        <div style="margin-top: 0.5rem;">
                            <div style="display: flex; justify-content: space-between; font-size: 0.875rem; margin-bottom: 0.25rem;">
                                <span>{{ number_format($minimax->quota_used) }} tokens</span>
                                <span>{{ number_format($minimax->getQuotaPercentage(), 1) }}%</span>
                            </div>
                            <div style="height: 0.5rem; width: 100%; border-radius: 9999px; background-color: rgb(229, 231, 235);">
                                <div
                                    style="height: 0.5rem; border-radius: 9999px; background-color: rgb(16, 185, 129); width: {{ min(100, $minimax->getQuotaPercentage()) }}%;"
                                ></div>
                            </div>
                            @if($minimax->quota_resets_at)
                                <p style="font-size: 0.75rem; color: rgb(107, 114, 128); margin-top: 0.25rem;">
                                    Resets {{ $minimax->quota_resets_at->diffForHumans() }}
                                </p>
                            @endif
                        </div>
                    </div>
                @endif

                <div style="display: flex; gap: 0.5rem; padding-top: 0.5rem;">
                    <x-filament::button wire:click="saveMinimaxSettings">
                        Save
                    </x-filament::button>
                    <x-filament::button color="gray" wire:click="resetQuota('minimax')" wire:confirm="Reset Minimax quota to zero?">
                        Reset Quota
                    </x-filament::button>
                </div>
            </div>
        </x-filament::section>

        {{-- Kimi Settings --}}
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg" style="background-color: rgba(139, 92, 246, 0.1);">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem; color: rgb(124, 58, 237);">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456ZM16.894 20.567 16.5 21.75l-.394-1.183a2.25 2.25 0 0 0-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 0 0 1.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 0 0 1.423 1.423l1.183.394-1.183.394a2.25 2.25 0 0 0-1.423 1.423Z" />
                        </svg>
                    </div>
                    <div>
                        <span>Kimi</span>
                        <p style="font-size: 0.75rem; font-weight: normal; color: rgb(107, 114, 128); margin: 0;">Kimi K2 (Moonshot AI)</p>
                    </div>
                </div>
            </x-slot>

            @php $kimi = $this->getKimiProvider(); @endphp

            <div style="display: flex; flex-direction: column; gap: 1rem;">
                <div>
                    <label style="font-size: 0.875rem; font-weight: 500;">Status</label>
                    <p style="margin-top: 0.25rem; font-size: 0.875rem; color: {{ $kimi?->is_active ? 'rgb(22, 163, 74)' : 'rgb(202, 138, 4)' }};">
                        {{ $kimi?->is_active ? 'Active' : 'Inactive (add API key to enable)' }}
                    </p>
                </div>

                <div>
                    <label style="font-size: 0.875rem; font-weight: 500;">API Key</label>
                    <input
                        type="password"
                        wire:model="kimiApiKey"
                        style="margin-top: 0.25rem; display: block; width: 100%; border-radius: 0.5rem; border: 1px solid rgb(209, 213, 219); padding: 0.5rem 0.75rem; font-size: 0.875rem;"
                        placeholder="Enter Kimi API key"
                    />
                </div>

                <div>
                    <label style="font-size: 0.875rem; font-weight: 500;">Model</label>
                    <input
                        type="text"
                        wire:model="kimiModel"
                        style="margin-top: 0.25rem; display: block; width: 100%; border-radius: 0.5rem; border: 1px solid rgb(209, 213, 219); padding: 0.5rem 0.75rem; font-size: 0.875rem;"
                        placeholder="kimi-k2.5 (default)"
                    />
                    <p style="font-size: 0.75rem; color: rgb(107, 114, 128); margin-top: 0.25rem;">
                        Available: kimi-k2.5
                    </p>
                </div>

                <div>
                    <label style="font-size: 0.875rem; font-weight: 500;">Monthly Quota Limit (tokens)</label>
                    <input
                        type="number"
                        min="0"
                        wire:model="kimiQuotaLimit"
                        style="margin-top: 0.25rem; display: block; width: 100%; border-radius: 0.5rem; border: 1px solid rgb(209, 213, 219); padding: 0.5rem 0.75rem; font-size: 0.875rem;"
                        placeholder="50000000"
                    />
                </div>

                <div>
                    <label style="font-size: 0.875rem; font-weight: 500;">Context Window (tokens)</label>
                    <input
                        type="number"
                        min="0"
                        wire:model="kimiContextWindow"
                        style="margin-top: 0.25rem; display: block; width: 100%; border-radius: 0.5rem; border: 1px solid rgb(209, 213, 219); padding: 0.5rem 0.75rem; font-size: 0.875rem;"
                        placeholder="262144 (default)"
                    />
                </div>

                @if($kimi)
                    <div>
                        <label style="font-size: 0.875rem; font-weight: 500;">Current Usage</label>
                        <div style="margin-top: 0.5rem;">
                            <div style="display: flex; justify-content: space-between; font-size: 0.875rem; margin-bottom: 0.25rem;">
                                <span>{{ number_format($kimi->quota_used) }} tokens</span>
                                <span>{{ number_format($kimi->getQuotaPercentage(), 1) }}%</span>
                            </div>
                            <div style="height: 0.5rem; width: 100%; border-radius: 9999px; background-color: rgb(229, 231, 235);">
                                <div
                                    style="height: 0.5rem; border-radius: 9999px; background-color: rgb(139, 92, 246); width: {{ min(100, $kimi->getQuotaPercentage()) }}%;"
                                ></div>
                            </div>
                            @if($kimi->quota_resets_at)
                                <p style="font-size: 0.75rem; color: rgb(107, 114, 128); margin-top: 0.25rem;">
                                    Resets {{ $kimi->quota_resets_at->diffForHumans() }}
                                </p>
                            @endif
                        </div>
                    </div>
                @endif

                <div style="display: flex; gap: 0.5rem; padding-top: 0.5rem;">
                    <x-filament::button wire:click="saveKimiSettings">
                        Save
                    </x-filament::button>
                    <x-filament::button color="gray" wire:click="resetQuota('kimi')" wire:confirm="Reset Kimi quota to zero?">
                        Reset Quota
                    </x-filament::button>
                </div>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
