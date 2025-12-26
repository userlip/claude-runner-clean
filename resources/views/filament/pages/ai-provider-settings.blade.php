<x-filament-panels::page>
    <div class="grid gap-6 md:grid-cols-2">
        {{-- Claude Settings --}}
        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex items-center gap-3 mb-4">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-orange-100 dark:bg-orange-900/20">
                    <x-heroicon-o-sparkles class="h-5 w-5 text-orange-600 dark:text-orange-400" />
                </div>
                <div>
                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">Claude</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Anthropic Claude Code</p>
                </div>
            </div>

            @php $claude = $this->getClaudeProvider(); @endphp

            <div class="space-y-4">
                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                    <p class="mt-1 text-sm {{ $claude?->is_active ? 'text-green-600' : 'text-gray-500' }}">
                        {{ $claude?->is_active ? 'Active (using default credentials)' : 'Inactive' }}
                    </p>
                </div>

                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Monthly Quota Limit (tokens)</label>
                    <input
                        type="number"
                        wire:model="claudeQuotaLimit"
                        class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        placeholder="10000000"
                    />
                </div>

                @if($claude)
                    <div>
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Current Usage</label>
                        <div class="mt-2">
                            <div class="flex justify-between text-sm mb-1">
                                <span>{{ number_format($claude->quota_used) }} tokens</span>
                                <span>{{ number_format($claude->getQuotaPercentage(), 1) }}%</span>
                            </div>
                            <div class="h-2 w-full rounded-full bg-gray-200 dark:bg-gray-700">
                                <div
                                    class="h-2 rounded-full bg-orange-500"
                                    style="width: {{ min(100, $claude->getQuotaPercentage()) }}%"
                                ></div>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="flex gap-2 pt-2">
                    <button
                        wire:click="saveClaudeSettings"
                        class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700"
                    >
                        Save
                    </button>
                    <button
                        wire:click="resetQuota('claude')"
                        wire:confirm="Reset Claude quota to zero?"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800"
                    >
                        Reset Quota
                    </button>
                </div>
            </div>
        </div>

        {{-- GLM Settings --}}
        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex items-center gap-3 mb-4">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/20">
                    <x-heroicon-o-bolt class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                </div>
                <div>
                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">GLM (z.ai)</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">GLM-4.6 via z.ai</p>
                </div>
            </div>

            @php $glm = $this->getGlmProvider(); @endphp

            <div class="space-y-4">
                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                    <p class="mt-1 text-sm {{ $glm?->is_active ? 'text-green-600' : 'text-yellow-600' }}">
                        {{ $glm?->is_active ? 'Active' : 'Inactive (add API key to enable)' }}
                    </p>
                </div>

                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">API Key</label>
                    <input
                        type="password"
                        wire:model="glmApiKey"
                        class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        placeholder="Enter z.ai API key"
                    />
                </div>

                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Quota Limit (tokens per 5-hour cycle)</label>
                    <input
                        type="number"
                        wire:model="glmQuotaLimit"
                        class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        placeholder="50000000"
                    />
                </div>

                @if($glm)
                    <div>
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Current Usage</label>
                        <div class="mt-2">
                            <div class="flex justify-between text-sm mb-1">
                                <span>{{ number_format($glm->quota_used) }} tokens</span>
                                <span>{{ number_format($glm->getQuotaPercentage(), 1) }}%</span>
                            </div>
                            <div class="h-2 w-full rounded-full bg-gray-200 dark:bg-gray-700">
                                <div
                                    class="h-2 rounded-full bg-blue-500"
                                    style="width: {{ min(100, $glm->getQuotaPercentage()) }}%"
                                ></div>
                            </div>
                            @if($glm->quota_resets_at)
                                <p class="text-xs text-gray-500 mt-1">
                                    Resets {{ $glm->quota_resets_at->diffForHumans() }}
                                </p>
                            @endif
                        </div>
                    </div>
                @endif

                <div class="flex gap-2 pt-2">
                    <button
                        wire:click="saveGlmSettings"
                        class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700"
                    >
                        Save
                    </button>
                    <button
                        wire:click="resetQuota('glm')"
                        wire:confirm="Reset GLM quota to zero?"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800"
                    >
                        Reset Quota
                    </button>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
