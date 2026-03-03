<x-filament-panels::page>
    <div
        class="chat-page-layout"
        x-data="{
            sidebarOpen: false,
            isMobile: window.innerWidth < 768,
            updateImmersiveMode() {
                if (this.isMobile) {
                    document.body.classList.add('chat-immersive-mode');
                } else {
                    document.body.classList.remove('chat-immersive-mode');
                }
            }
        }"
        x-init="
            updateImmersiveMode();
            window.addEventListener('resize', () => {
                isMobile = window.innerWidth < 768;
                updateImmersiveMode();
            });
        "
        @open-sidebar.window="sidebarOpen = true"
    >
        {{-- Mobile Sidebar Toggle --}}
        <button
            @click="sidebarOpen = true"
            class="chat-sidebar-toggle"
            title="Open Files & Snippets"
        >
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9.776c.112-.017.227-.026.344-.026h15.812c.117 0 .232.009.344.026m-16.5 0a2.25 2.25 0 00-1.883 2.542l.857 6a2.25 2.25 0 002.227 1.932H19.05a2.25 2.25 0 002.227-1.932l.857-6a2.25 2.25 0 00-1.883-2.542m-16.5 0V6A2.25 2.25 0 016 3.75h3.879a1.5 1.5 0 011.06.44l2.122 2.12a1.5 1.5 0 001.06.44H18A2.25 2.25 0 0120.25 9v.776" />
            </svg>
            <span>Files</span>
        </button>

        {{-- Chat Area --}}
        <div class="chat-page-main">
            @livewire('task-chat', ['task' => $this->getRecord()])
        </div>

        {{-- Mobile Sidebar Overlay --}}
        <div
            x-show="sidebarOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            @click="sidebarOpen = false"
            class="chat-sidebar-overlay"
            x-cloak
        ></div>

        {{-- Sidebar with Tabs --}}
        <div
            class="chat-page-sidebar"
            :class="{ 'chat-page-sidebar-open': sidebarOpen }"
            x-data="{ activeTab: 'snippets' }"
        >
            <button @click="sidebarOpen = false" class="chat-sidebar-close">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
            <div class="chat-sidebar-title">Workspace Panels</div>
            <div class="sidebar-tabs">
                <div class="sidebar-tab-buttons">
                    <button
                        @click="activeTab = 'snippets'"
                        :class="{ 'sidebar-tab-btn-active': activeTab === 'snippets' }"
                        class="sidebar-tab-btn"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="sidebar-tab-btn-icon">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625a1.125 1.125 0 0 0-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                        </svg>
                        <span class="sidebar-tab-btn-label">Snippets</span>
                    </button>
                    <button
                        @click="activeTab = 'files'"
                        :class="{ 'sidebar-tab-btn-active': activeTab === 'files' }"
                        class="sidebar-tab-btn"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="sidebar-tab-btn-icon">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9.776c.112-.017.227-.026.344-.026h15.812c.117 0 .232.009.344.026M3.75 9.776A2.25 2.25 0 0 0 1.867 12.32l.857 6a2.25 2.25 0 0 0 2.227 1.932H19.05a2.25 2.25 0 0 0 2.227-1.932l.857-6a2.25 2.25 0 0 0-1.883-2.542M3.75 9.776V6A2.25 2.25 0 0 1 6 3.75h3.879a1.5 1.5 0 0 1 1.06.44l2.122 2.12a1.5 1.5 0 0 0 1.06.44H18A2.25 2.25 0 0 1 20.25 9v.776" />
                        </svg>
                        <span class="sidebar-tab-btn-label">Files</span>
                    </button>
                    <button
                        @click="activeTab = 'session'"
                        :class="{ 'sidebar-tab-btn-active': activeTab === 'session' }"
                        class="sidebar-tab-btn"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="sidebar-tab-btn-icon">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h12a2.25 2.25 0 0 0 2.25-2.25V3M3.75 3h16.5M3.75 7.5h16.5M8.25 21h7.5" />
                        </svg>
                        <span class="sidebar-tab-btn-label">Session</span>
                    </button>
                    <button
                        @click="activeTab = 'ralph'"
                        :class="{ 'sidebar-tab-btn-active': activeTab === 'ralph' }"
                        class="sidebar-tab-btn"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="sidebar-tab-btn-icon">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-2.813-.813a4.5 4.5 0 0 1-2.437-6.845l7.012-7.012a4.5 4.5 0 0 1 6.364 0l2.794 2.794a4.5 4.5 0 0 1 0 6.364l-7.012 7.012a4.5 4.5 0 0 1-6.845-2.437Z" />
                        </svg>
                        <span class="sidebar-tab-btn-label">Ralph</span>
                    </button>
                </div>
                <div class="sidebar-tab-content">
                    <div x-show="activeTab === 'snippets'" class="sidebar-tab-pane">
                        @livewire('snippet-browser')
                    </div>
                    <div x-show="activeTab === 'files'" x-cloak class="sidebar-tab-pane">
                        @livewire('file-browser', ['basePath' => $this->getRecord()->workspace_path ?? $this->getRecord()->site?->path])
                    </div>
                    <div x-show="activeTab === 'session'" x-cloak class="sidebar-tab-pane">
                        @livewire('session-info-sidebar', ['task' => $this->getRecord()])
                    </div>
                    <div x-show="activeTab === 'ralph'" x-cloak class="sidebar-tab-pane">
                        @livewire('ralph-control-panel', ['task' => $this->getRecord()])
                    </div>
                </div>
            </div>
            {{-- Desktop Todo List (bottom half of sidebar) --}}
            @livewire('task-todo-list', ['task' => $this->getRecord()])
        </div>
    </div>
</x-filament-panels::page>
