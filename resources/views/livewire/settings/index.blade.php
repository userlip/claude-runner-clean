<div>
    <x-header title="Settings" separator />

    <div class="flex flex-col gap-6">
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
    </div>
</div>
