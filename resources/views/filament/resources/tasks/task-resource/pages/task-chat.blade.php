<x-filament-panels::page>
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; height: calc(100vh - 16rem); overflow: hidden;">
        {{-- Chat Area (2/3 width) --}}
        <div style="height: 100%; min-height: 0; overflow: hidden;">
            <div style="height: 100%;">
                @livewire('task-chat', ['task' => $this->getRecord()])
            </div>
        </div>

        {{-- Sidebar with Tabs (1/3 width) --}}
        <div style="height: 100%; min-height: 0; overflow: hidden;">
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
