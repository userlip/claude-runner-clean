<x-filament-panels::page>
    <div class="chat-page-layout">
        {{-- Chat Area --}}
        <div class="chat-page-main">
            @livewire('task-chat', ['task' => $this->getRecord()])
        </div>

        {{-- Sidebar with Tabs --}}
        <div class="chat-page-sidebar">
            <div class="sidebar-tabs" x-data="{ activeTab: 'files' }">
                <div class="sidebar-tab-buttons">
                    <button
                        @click="activeTab = 'files'"
                        :class="{ 'sidebar-tab-btn-active': activeTab === 'files' }"
                        class="sidebar-tab-btn"
                    >
                        Files
                    </button>
                    <button
                        @click="activeTab = 'snippets'"
                        :class="{ 'sidebar-tab-btn-active': activeTab === 'snippets' }"
                        class="sidebar-tab-btn"
                    >
                        Snippets
                    </button>
                </div>
                <div class="sidebar-tab-content">
                    <div x-show="activeTab === 'files'" style="height: 100%;">
                        @livewire('file-browser', ['basePath' => $this->getRecord()->workspace_path ?? $this->getRecord()->site?->path])
                    </div>
                    <div x-show="activeTab === 'snippets'" x-cloak style="height: 100%;">
                        @livewire('snippet-browser')
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
