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
            <div class="sidebar-tabs">
                <div class="sidebar-tab-buttons">
                    <button
                        @click="activeTab = 'snippets'"
                        :class="{ 'sidebar-tab-btn-active': activeTab === 'snippets' }"
                        class="sidebar-tab-btn"
                    >
                        Snippets
                    </button>
                    <button
                        @click="activeTab = 'files'"
                        :class="{ 'sidebar-tab-btn-active': activeTab === 'files' }"
                        class="sidebar-tab-btn"
                    >
                        Files
                    </button>
                </div>
                <div class="sidebar-tab-content">
                    <div x-show="activeTab === 'snippets'" style="height: 100%;">
                        @livewire('snippet-browser')
                    </div>
                    <div x-show="activeTab === 'files'" x-cloak style="height: 100%;">
                        @livewire('file-browser', ['basePath' => $this->getRecord()->workspace_path ?? $this->getRecord()->site?->path])
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
