<div
    x-data="{ openPanels: @entangle('openPanels') }"
    class="flex"
    style="height: calc(100vh - 65px);"
>
    {{-- Main Chat Area --}}
    <div class="flex-1 min-w-0 overflow-hidden">
        <livewire:task-chat :task="$task" :key="'task-chat-'.$task->uuid" />
    </div>

    {{-- Side Panels (hidden on mobile) --}}
    <div class="hidden lg:flex w-80 border-l border-base-300 flex-col bg-base-100 shrink-0">
        {{-- Panel Toggle Tabs --}}
        <div class="flex flex-wrap gap-1 p-2 border-b border-base-300">
            @foreach(\App\Livewire\Settings\Index::AVAILABLE_PANELS as $panelKey => $panel)
                <button
                    wire:click="togglePanel('{{ $panelKey }}')"
                    class="btn btn-xs"
                    :class="openPanels.includes('{{ $panelKey }}') ? 'btn-primary' : 'btn-ghost'"
                >
                    {{ $panel['title'] }}
                </button>
            @endforeach
        </div>

        {{-- Panels: rendered in the order they appear in openPanels --}}
        <div class="flex-1 flex flex-col min-h-0">
            <template x-if="openPanels.length === 0">
                <div class="flex-1 flex items-center justify-center text-base-content/40 text-sm">
                    Click a tab to open a panel
                </div>
            </template>

            @foreach(\App\Livewire\Settings\Index::AVAILABLE_PANELS as $panelKey => $panel)
                <div
                    x-show="openPanels.includes('{{ $panelKey }}')"
                    x-cloak
                    class="overflow-auto p-2 border-b border-base-300 last:border-b-0"
                    :style="`flex: 1 1 0%; min-height: 0; order: ${openPanels.indexOf('{{ $panelKey }}')}`"
                >
                    @switch($panelKey)
                        @case('session-info')
                            <livewire:session-info-sidebar :task="$task" :key="'session-info-'.$task->uuid" lazy />
                            @break
                        @case('file-browser')
                            <livewire:file-browser :basePath="$task->working_directory" :key="'file-browser-'.$task->uuid" lazy />
                            @break
                        @case('snippet-browser')
                            <livewire:snippet-browser :key="'snippet-browser-'.$task->uuid" lazy />
                            @break
                        @case('todo-list')
                            <livewire:task-todo-list :task="$task" :key="'todo-list-'.$task->uuid" lazy />
                            @break
                        @case('todo-mobile')
                            <livewire:task-todo-mobile :task="$task" :key="'todo-mobile-'.$task->uuid" lazy />
                            @break
                        @case('ralph')
                            <livewire:ralph-control-panel :task="$task" :key="'ralph-'.$task->uuid" lazy />
                            @break
                    @endswitch
                </div>
            @endforeach
        </div>
    </div>
</div>
