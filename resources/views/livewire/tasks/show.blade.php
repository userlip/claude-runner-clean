<div
    x-data="{ activePanel: @entangle('activePanel') }"
    class="p-4"
>
    <div class="mb-4">
        <h1 class="text-xl font-semibold">{{ $task->title ?? ($task->repository?->name ?? 'Task') }}</h1>
        <p class="text-sm text-gray-500">{{ $task->status->value }}</p>
    </div>

    {{-- Panel Toggle Tabs --}}
    <div class="flex flex-wrap gap-2 mb-4 border-b border-gray-200 dark:border-gray-700 pb-2">
        <button
            wire:click="setActivePanel('session-info')"
            class="px-3 py-1 text-sm rounded"
            :class="activePanel === 'session-info' ? 'bg-primary-600 text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300'"
        >
            Session Info
        </button>
        <button
            wire:click="setActivePanel('file-browser')"
            class="px-3 py-1 text-sm rounded"
            :class="activePanel === 'file-browser' ? 'bg-primary-600 text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300'"
        >
            Files
        </button>
        <button
            wire:click="setActivePanel('snippet-browser')"
            class="px-3 py-1 text-sm rounded"
            :class="activePanel === 'snippet-browser' ? 'bg-primary-600 text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300'"
        >
            Snippets
        </button>
        <button
            wire:click="setActivePanel('todo-list')"
            class="px-3 py-1 text-sm rounded"
            :class="activePanel === 'todo-list' ? 'bg-primary-600 text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300'"
        >
            To-Do
        </button>
        <button
            wire:click="setActivePanel('todo-mobile')"
            class="px-3 py-1 text-sm rounded"
            :class="activePanel === 'todo-mobile' ? 'bg-primary-600 text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300'"
        >
            To-Do (Mobile)
        </button>
        <button
            wire:click="setActivePanel('ralph')"
            class="px-3 py-1 text-sm rounded"
            :class="activePanel === 'ralph' ? 'bg-primary-600 text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300'"
        >
            Ralph
        </button>
    </div>

    {{-- Panels (lazy-loaded, shown based on active tab) --}}
    <div x-show="activePanel === 'session-info'" x-cloak>
        <livewire:session-info-sidebar :task="$task" :key="'session-info-'.$task->uuid" lazy />
    </div>

    <div x-show="activePanel === 'file-browser'" x-cloak>
        <livewire:file-browser :basePath="$task->working_directory" :key="'file-browser-'.$task->uuid" lazy />
    </div>

    <div x-show="activePanel === 'snippet-browser'" x-cloak>
        <livewire:snippet-browser :key="'snippet-browser-'.$task->uuid" lazy />
    </div>

    <div x-show="activePanel === 'todo-list'" x-cloak>
        <livewire:task-todo-list :task="$task" :key="'todo-list-'.$task->uuid" lazy />
    </div>

    <div x-show="activePanel === 'todo-mobile'" x-cloak>
        <livewire:task-todo-mobile :task="$task" :key="'todo-mobile-'.$task->uuid" lazy />
    </div>

    <div x-show="activePanel === 'ralph'" x-cloak>
        <livewire:ralph-control-panel :task="$task" :key="'ralph-'.$task->uuid" lazy />
    </div>
</div>
