<div>
    <x-header title="Settings" separator />

    <div class="flex flex-col gap-6">
        @if(auth()->user()->hasRole('admin'))
            <x-card shadow>
                <x-header title="Admin Tools" subtitle="Shared system-level configuration for the workbench runtime." size="text-lg" class="mb-4" separator />

                <div class="flex flex-wrap gap-2">
                    <x-button label="AI Providers" icon="o-cpu-chip" link="{{ route('workbench.ai-providers.index') }}" class="btn-ghost btn-sm" />
                    <x-button label="MCP Servers" icon="o-server-stack" link="{{ route('workbench.settings.mcp') }}" class="btn-primary btn-sm" />
                </div>
            </x-card>
        @endif

        {{-- Sidebar Panels --}}
        <x-card shadow>
            <x-header title="Default Sidebar Panels" subtitle="Choose up to 3 panels to open by default when you enter a chat. Multiple panels split the sidebar vertically." size="text-lg" class="mb-4" separator />

            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                @foreach(\App\Livewire\Settings\Index::AVAILABLE_PANELS as $key => $panel)
                    @php
                        $isSelected = in_array($key, $sidebarPanels);
                        $isDisabled = !$isSelected && count($sidebarPanels) >= 3;
                        $order = $isSelected ? array_search($key, $sidebarPanels) + 1 : null;
                    @endphp
                    <button
                        wire:click="togglePanel('{{ $key }}')"
                        class="flex items-center gap-2 px-3 py-2.5 rounded-lg border text-sm transition-all
                            {{ $isSelected
                                ? 'border-primary bg-primary/10 text-primary font-medium'
                                : ($isDisabled
                                    ? 'border-base-300 text-base-content/30 cursor-not-allowed'
                                    : 'border-base-300 text-base-content/60 hover:border-base-content/30 hover:text-base-content') }}"
                        @if($isDisabled) disabled @endif
                    >
                        <x-icon :name="$panel['icon']" class="size-4" />
                        {{ $panel['title'] }}
                        @if($isSelected)
                            <span class="ml-auto badge badge-xs badge-primary">{{ $order }}</span>
                        @endif
                    </button>
                @endforeach
            </div>

            @if(count($sidebarPanels) === 0)
                <div class="text-xs text-warning mt-2">Select at least one panel to show in the sidebar.</div>
            @endif
        </x-card>

        {{-- Other Settings --}}
        <x-card shadow>
            <x-header title="General" size="text-lg" class="mb-4" separator />

            <div class="flex flex-col gap-4">
                <x-toggle
                    label="Show Recent Chats"
                    hint="Display recent chats in the sidebar."
                    wire:model.live="showRecentChats"
                />
                @if($showRecentChats)
                    <x-toggle
                        label="Pin Recent Chats"
                        hint="Pin recent chats to the bottom of the sidebar. When unpinned, they scroll with the menu."
                        wire:model.live="pinRecentChats"
                    />
                    <x-input
                        label="Recent Chats Limit"
                        hint="How many recent chats to show (1–50)."
                        type="number"
                        min="1"
                        max="50"
                        wire:model.live.debounce.500ms="recentChatsLimit"
                        class="w-24"
                    />
                @endif
            </div>
        </x-card>

        {{-- System Triggers --}}
        <x-card shadow>
            <x-header title="System Triggers" subtitle="Automated behaviors that run in the background." size="text-lg" class="mb-4" separator />

            <div class="flex flex-col gap-4">
                <x-toggle
                    label="GitHub PR Polling"
                    hint="Poll GitHub every minute for CI results on detected PRs."
                    wire:model.live="triggerPrPollingEnabled"
                />

                @if($triggerPrPollingEnabled)
                    <x-toggle
                        label="Auto-Detect PRs"
                        hint="Automatically detect PR URLs in task messages and start monitoring them."
                        wire:model.live="triggerPrDetectionEnabled"
                    />

                    <x-toggle
                        label="Auto-Nudge on CI Finish"
                        hint="Send an automatic message to the task chat when CI finishes with results and review info."
                        wire:model.live="triggerPrNudgeEnabled"
                    />

                    <x-input
                        label="Skip These Checks"
                        hint="Comma-separated keywords. Checks matching these words won't block the nudge — the system won't wait for them to finish. All other checks must complete first."
                        wire:model="triggerPrIgnoredChecks"
                        placeholder="claude"
                    />

                    @if($triggerPrNudgeEnabled)
                        <x-textarea
                            label="Nudge Instruction"
                            hint="The instruction appended to the auto-nudge message. Tells the agent what to do when CI finishes."
                            wire:model="triggerPrNudgeTemplate"
                            rows="3"
                        />
                    @endif

                    <div class="flex gap-2">
                        <x-button label="Save" wire:click="saveTriggerSettings" spinner="saveTriggerSettings" class="btn-primary btn-sm" />
                        <x-button label="Reset to Defaults" wire:click="resetNudgeTemplate" spinner="resetNudgeTemplate" class="btn-ghost btn-sm" />
                    </div>
                @endif
            </div>
        </x-card>
    </div>
</div>
