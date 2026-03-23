<div
    x-data="{ activePanel: $wire.entangle('activePanel') }"
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
        <div class="flex gap-1 p-2 border-b border-base-300 overflow-x-auto shrink-0 scrollbar-none">
            @foreach(\App\Livewire\Settings\Index::AVAILABLE_PANELS as $panelKey => $panel)
                <button
                    @click="activePanel = activePanel === '{{ $panelKey }}' ? null : '{{ $panelKey }}'"
                    class="btn btn-xs shrink-0"
                    :class="activePanel === '{{ $panelKey }}' ? 'btn-primary' : 'btn-ghost'"
                >
                    {{ $panel['title'] }}
                </button>
            @endforeach
        </div>

        {{-- Single active panel --}}
        <div class="flex-1 flex flex-col min-h-0">
            <template x-if="activePanel === null">
                <div class="flex-1 flex items-center justify-center text-base-content/40 text-sm">
                    Click a tab to open a panel
                </div>
            </template>

            @foreach(\App\Livewire\Settings\Index::AVAILABLE_PANELS as $panelKey => $panel)
                <div
                    x-show="activePanel === '{{ $panelKey }}'"
                    x-cloak
                    class="flex-1 overflow-auto p-2"
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
