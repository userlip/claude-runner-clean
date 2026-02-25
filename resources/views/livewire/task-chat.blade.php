<div class="chat-container" x-data="{
    mobileMenuOpen: false,
    taskUuid: '{{ $task->uuid }}',
    init() {
        // Make all links in chat bubbles open in new tab
        const makeLinksExternal = () => {
            this.$el.querySelectorAll('.chat-bubble-content a').forEach(link => {
                link.setAttribute('target', '_blank');
                link.setAttribute('rel', 'noopener noreferrer');
            });
        };
        makeLinksExternal();
        // Re-run when Livewire updates the DOM
        Livewire.hook('morph.updated', ({ el }) => {
            if (this.$el.contains(el) || this.$el === el) {
                makeLinksExternal();
            }
        });

        // Handle iOS keyboard showing/hiding
        if (window.visualViewport) {
            const header = this.$el.querySelector('.chat-mobile-header');
            const initialHeight = window.visualViewport.height;

            const handleViewportChange = () => {
                // Detect keyboard by checking if viewport shrunk significantly (>150px for keyboard)
                const heightDiff = initialHeight - window.visualViewport.height;
                const keyboardVisible = heightDiff > 150;

                // Toggle keyboard class for CSS adjustments
                document.body.classList.toggle('keyboard-visible', keyboardVisible);

                // Keep header at top of visual viewport
                if (header) {
                    header.style.top = window.visualViewport.offsetTop + 'px';
                }
            };

            window.visualViewport.addEventListener('resize', handleViewportChange);
            window.visualViewport.addEventListener('scroll', handleViewportChange);
        }
    }
}">
    {{-- Mobile Header (shown only on mobile in immersive mode) --}}
    <div class="chat-mobile-header">
        <a
            href="{{ route('filament.admin.resources.tasks.index') }}"
            class="chat-mobile-back"
            wire:navigate
        >
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 1.5rem; height: 1.5rem;">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
            </svg>
        </a>
        <div class="chat-mobile-title-area">
            <h1 class="chat-mobile-title">{{ $task->title ?? ($task->repository?->name ?? 'Chat') }}</h1>
            @if($task->taskSchedule)
                <span class="inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-xs text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">
                    Scheduled: {{ $task->taskSchedule->name }}
                </span>
            @endif
            <p class="chat-mobile-subtitle">
                {{ $this->locationLabel }}
                @if($task->isInWorkspace() && $task->isInitializing())
                    <span class="chat-mobile-init-status">
                        @switch($task->init_status)
                            @case('cloning')
                                · Cloning...
                                @break
                            @case('composer_install')
                                · Composer...
                                @break
                            @case('npm_install')
                                · npm...
                                @break
                            @case('npm_build')
                                · Building...
                                @break
                            @default
                                · Initializing...
                        @endswitch
                    </span>
                @endif
            </p>
        </div>
        {{-- Context indicator --}}
        <div class="chat-mobile-context" title="{{ $task->is_compacting ? 'Compacting conversation...' : number_format($this->contextUsed) . ' / ' . number_format($this->contextLimit) . ' tokens' }}">
            @if($task->is_compacting)
                <div class="flex items-center gap-1 px-1.5 py-0.5 bg-amber-100 dark:bg-amber-900/30 rounded">
                    <svg class="w-3 h-3 text-amber-600 dark:text-amber-400 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
            @else
                <div class="chat-mobile-context-bar">
                    <div class="chat-mobile-context-fill {{ $this->contextColor }}" style="width: {{ min($this->contextPercentage, 100) }}%"></div>
                </div>
                <span class="chat-mobile-context-text">{{ number_format($this->contextPercentage, 0) }}%</span>
            @endif
            @if($task->compaction_count > 0)
                <span class="inline-flex items-center justify-center w-5 h-5 text-xs font-medium rounded-full bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300">
                    {{ $task->compaction_count }}
                </span>
            @endif
        </div>
        {{-- Menu button --}}
        <button @click="mobileMenuOpen = !mobileMenuOpen" class="chat-mobile-menu">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.5rem; height: 1.5rem;">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 12.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 18.75a.75.75 0 110-1.5.75.75 0 010 1.5z" />
            </svg>
        </button>
        {{-- Mobile Dropdown Menu --}}
        <div
            x-show="mobileMenuOpen"
            @click.away="mobileMenuOpen = false"
            x-transition:enter="transition ease-out duration-100"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="chat-mobile-dropdown"
            x-cloak
        >
            {{-- Provider selector --}}
            <div class="chat-mobile-providers">
                @foreach($this->availableProviders as $provider)
                    <button
                        wire:click="setProvider({{ $provider->id }})"
                        @click="mobileMenuOpen = false"
                        class="chat-provider-btn {{ $this->currentProvider?->id === $provider->id ? 'chat-provider-btn-active' : '' }}"
                        @disabled($this->isRunning)
                    >
                        {{ $provider->display_name }}
                    </button>
                @endforeach
            </div>
            {{-- Actions --}}
            @if($this->chatMessages->isNotEmpty())
                <button
                    wire:click="generateTitle"
                    @click="mobileMenuOpen = false"
                    wire:loading.attr="disabled"
                    wire:target="generateTitle"
                    class="chat-mobile-dropdown-item"
                >
                    <span>✨</span>
                    <span wire:loading.remove wire:target="generateTitle">Rename Chat</span>
                    <span wire:loading wire:target="generateTitle">Renaming...</span>
                </button>
            @endif
            <button
                @click="$dispatch('open-sidebar'); mobileMenuOpen = false"
                class="chat-mobile-dropdown-item"
            >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9.776c.112-.017.227-.026.344-.026h15.812c.117 0 .232.009.344.026m-16.5 0a2.25 2.25 0 00-1.883 2.542l.857 6a2.25 2.25 0 002.227 1.932H19.05a2.25 2.25 0 002.227-1.932l.857-6a2.25 2.25 0 00-1.883-2.542m-16.5 0V6A2.25 2.25 0 016 3.75h3.879a1.5 1.5 0 011.06.44l2.122 2.12a1.5 1.5 0 001.06.44H18A2.25 2.25 0 0120.25 9v.776" />
                </svg>
                <span>Snippets & Files</span>
            </button>
            <a
                href="{{ \App\Filament\Resources\Tasks\Pages\TaskIde::getUrl(['record' => $task]) }}"
                @click="mobileMenuOpen = false"
                class="chat-mobile-dropdown-item"
            >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 6.75L22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3l-4.5 16.5" />
                </svg>
                <span>Open IDE</span>
            </a>
            @if($task->isInWorkspace())
                <div class="chat-mobile-dropdown-divider"></div>
                @if($this->hasEnvConfigs)
                    <button
                        wire:click="copyEnvConfig"
                        @click="mobileMenuOpen = false"
                        class="chat-mobile-dropdown-item"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 01-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 011.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 00-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 01-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 00-3.375-3.375h-1.5a1.125 1.125 0 01-1.125-1.125v-1.5a3.375 3.375 0 00-3.375-3.375H9.75" />
                        </svg>
                        <span>Copy .env</span>
                    </button>
                @endif
                <button
                    wire:click="openDeployModal"
                    @click="mobileMenuOpen = false"
                    class="chat-mobile-dropdown-item"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem; color: rgb(22 163 74);">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0l3 3m-3-3l-3 3M6.75 19.5a4.5 4.5 0 01-1.41-8.775 5.25 5.25 0 0110.233-2.33 3 3 0 013.758 3.848A3.752 3.752 0 0118 19.5H6.75z" />
                    </svg>
                    <span>Deploy to Site</span>
                </button>
                <div class="chat-mobile-dropdown-divider"></div>
                <button
                    wire:click="deleteWorkspace"
                    wire:confirm="Are you sure you want to delete this workspace? This cannot be undone."
                    @click="mobileMenuOpen = false"
                    class="chat-mobile-dropdown-item chat-mobile-dropdown-item-danger"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                    </svg>
                    <span>Delete Workspace</span>
                </button>
            @endif
            {{-- Delete Chat (always available) --}}
            <div class="chat-mobile-dropdown-divider"></div>
            <button
                wire:click="deleteTask"
                wire:confirm="Are you sure you want to delete this chat and all its messages? This cannot be undone."
                @click="mobileMenuOpen = false"
                class="chat-mobile-dropdown-item chat-mobile-dropdown-item-danger"
            >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                </svg>
                <span>Delete Chat</span>
            </button>
        </div>
    </div>

    {{-- Desktop Header --}}
    <div class="chat-header">
        {{-- Top row: Title and Provider selector --}}
        <div class="chat-header-top">
            <div class="chat-header-info">
                <div class="chat-header-title-row">
                    <h2 class="chat-header-title">{{ $task->title ?? ($task->repository?->name ?? 'Chat') }}</h2>
                    @if($this->chatMessages->isNotEmpty())
                        <button
                            wire:click="generateTitle"
                            wire:loading.attr="disabled"
                            wire:target="generateTitle"
                            title="Generate title from conversation"
                            class="chat-rename-btn"
                        >
                            <span wire:loading.remove wire:target="generateTitle">✨ Rename</span>
                            <span wire:loading wire:target="generateTitle">✨ ...</span>
                        </button>
                        @if($task->repository)
                            <button
                                wire:click="triggerPrdToIssues"
                                wire:loading.attr="disabled"
                                wire:target="triggerPrdToIssues"
                                title="Break PRD into GitHub Issues"
                                class="chat-rename-btn"
                            >
                                <span wire:loading.remove wire:target="triggerPrdToIssues">🎯 PRD → Issues</span>
                                <span wire:loading wire:target="triggerPrdToIssues">🎯 ...</span>
                            </button>
                            @if(!$task->ralph_enabled)
                                <button
                                    wire:click="startRalphLoop"
                                    wire:loading.attr="disabled"
                                    wire:target="startRalphLoop"
                                    title="Import PRD slices and start Ralph autonomous loop"
                                    class="chat-rename-btn"
                                >
                                    <span wire:loading.remove wire:target="startRalphLoop">🔁 Start Ralph</span>
                                    <span wire:loading wire:target="startRalphLoop">🔁 ...</span>
                                </button>
                            @else
                                @php $ralph = $this->ralphStatus; @endphp
                                <span
                                    class="chat-rename-btn chat-ralph-status"
                                    wire:poll.10s
                                    title="Ralph loop: Iteration {{ $ralph['iteration'] ?? '?' }} | {{ $ralph['stories_passed'] ?? 0 }}/{{ $ralph['stories_total'] ?? 0 }} stories passed"
                                >
                                    @if(($ralph['status'] ?? '') === 'completed')
                                        ✅ Ralph Done ({{ $ralph['stories_passed'] ?? 0 }}/{{ $ralph['stories_total'] ?? 0 }})
                                    @elseif(($ralph['status'] ?? '') === 'failed')
                                        ❌ Ralph Failed ({{ $ralph['stories_passed'] ?? 0 }}/{{ $ralph['stories_total'] ?? 0 }})
                                    @else
                                        <svg class="animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" style="width: 0.75rem; height: 0.75rem; display: inline;">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        Ralph #{{ $ralph['iteration'] ?? '?' }} ({{ $ralph['stories_passed'] ?? 0 }}/{{ $ralph['stories_total'] ?? 0 }})
                                    @endif
                                </span>
                            @endif
                        @endif
                    @endif
                </div>
                <div class="chat-header-meta">
                    <span class="chat-header-subtitle">{{ $this->locationLabel }}</span>
                    {{-- Init Status Indicators --}}
                    @if($task->isInWorkspace())
                        <span class="chat-header-separator">·</span>
                        <div class="chat-init-status" @if($task->isInitializing()) wire:poll.5s @endif>
                            @if($task->isInitializing())
                                <span class="chat-init-badge chat-init-running" title="Initializing workspace...">
                                    <svg class="animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" style="width: 0.75rem; height: 0.75rem;">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    @switch($task->init_status)
                                        @case('cloning')
                                            Cloning...
                                            @break
                                        @case('composer_install')
                                            Composer...
                                            @break
                                        @case('npm_install')
                                            npm install...
                                            @break
                                        @case('npm_build')
                                            Building...
                                            @break
                                        @default
                                            Initializing...
                                    @endswitch
                                </span>
                            @else
                                @if($task->ran_composer_install)
                                    <span class="chat-init-badge chat-init-done" title="Composer install completed">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width: 0.75rem; height: 0.75rem;">
                                            <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
                                        </svg>
                                        composer
                                    </span>
                                @endif
                                @if($task->ran_npm_install)
                                    <span class="chat-init-badge chat-init-done" title="npm install completed">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width: 0.75rem; height: 0.75rem;">
                                            <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
                                        </svg>
                                        npm
                                    </span>
                                @endif
                                @if($task->ran_npm_build)
                                    <span class="chat-init-badge chat-init-done" title="npm build completed">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width: 0.75rem; height: 0.75rem;">
                                            <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
                                        </svg>
                                        build
                                    </span>
                                @endif
                            @endif
                        </div>
                    @endif
                    {{-- Context Usage Indicator (inline with subtitle) --}}
                    <span class="chat-header-separator">·</span>
                    @if($task->is_compacting)
                        <span class="chat-context-compacting">
                            <svg class="animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Compacting...
                        </span>
                    @else
                        <span class="chat-context-usage" title="{{ number_format($this->contextUsed) }} / {{ number_format($this->contextLimit) }} tokens">
                            <span class="chat-context-bar">
                                <span class="chat-context-fill {{ $this->contextColor }}" style="width: {{ min($this->contextPercentage, 100) }}%"></span>
                            </span>
                            {{ number_format($this->contextPercentage, 0) }}%
                        </span>
                    @endif
                    @if($task->compaction_count > 0)
                        <span class="chat-compaction-count" title="{{ $task->compaction_count }} {{ Str::plural('compaction', $task->compaction_count) }}">
                            ×{{ $task->compaction_count }}
                        </span>
                    @endif
                </div>
            </div>
            <div class="chat-provider-selector">
                @foreach($this->availableProviders as $provider)
                    <button
                        wire:click="setProvider({{ $provider->id }})"
                        class="chat-provider-btn {{ $this->currentProvider?->id === $provider->id ? 'chat-provider-btn-active' : '' }}"
                        @disabled($this->isRunning)
                    >
                        {{ $provider->display_name }}
                    </button>
                @endforeach
            </div>
        </div>
        {{-- Bottom row: Action buttons (only for workspaces) --}}
        @if($task->isInWorkspace())
            <div class="chat-header-actions">
                <a
                    href="{{ \App\Filament\Resources\Tasks\Pages\TaskIde::getUrl(['record' => $task]) }}"
                    class="chat-header-action-btn"
                    title="Open in IDE"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="chat-header-action-icon">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 6.75L22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3l-4.5 16.5" />
                    </svg>
                    <span>IDE</span>
                </a>
                @if($this->hasEnvConfigs)
                    <div x-data="{ open: false }" class="relative">
                        <button
                            type="button"
                            wire:click="copyEnvConfig"
                            @click.away="open = false"
                            class="chat-header-action-btn"
                        >
                            <x-heroicon-o-document-duplicate class="chat-header-action-icon" />
                            <span>Copy .env</span>
                            @if($this->envConfigs->count() > 1)
                                <button
                                    type="button"
                                    @click.stop="open = !open"
                                    class="chat-header-action-dropdown"
                                >
                                    <x-heroicon-o-chevron-down class="chat-header-action-icon" />
                                </button>
                            @endif
                        </button>

                        @if($this->envConfigs->count() > 1)
                            <div
                                x-show="open"
                                x-transition
                                class="chat-header-dropdown"
                            >
                                @foreach($this->envConfigs as $config)
                                    <button
                                        type="button"
                                        wire:click="copyEnvConfig({{ $config->id }})"
                                        @click="open = false"
                                        class="chat-header-dropdown-item"
                                    >
                                        @if($config->is_default)
                                            <x-heroicon-o-star class="chat-header-dropdown-icon text-yellow-500" />
                                        @endif
                                        {{ $config->name }}
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif
                <button wire:click="openDeployModal" class="chat-header-action-btn chat-header-action-btn-success">
                    <x-heroicon-o-cloud-arrow-up class="chat-header-action-icon" />
                    <span>Deploy</span>
                </button>
                <button
                    wire:click="deleteWorkspace"
                    wire:confirm="Are you sure you want to delete this workspace? This cannot be undone."
                    class="chat-header-action-btn chat-header-action-btn-danger"
                >
                    <x-heroicon-o-trash class="chat-header-action-icon" />
                    <span>Delete Workspace</span>
                </button>
                <button
                    wire:click="deleteTask"
                    wire:confirm="Are you sure you want to delete this chat and all its messages? This cannot be undone."
                    class="chat-header-action-btn chat-header-action-btn-danger"
                >
                    <x-heroicon-o-trash class="chat-header-action-icon" />
                    <span>Delete Chat</span>
                </button>
            </div>
        @else
            {{-- IDE + Delete Chat buttons (no workspace) --}}
            <div class="chat-header-actions">
                <a
                    href="{{ \App\Filament\Resources\Tasks\Pages\TaskIde::getUrl(['record' => $task]) }}"
                    class="chat-header-action-btn"
                    title="Open in IDE"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="chat-header-action-icon">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 6.75L22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3l-4.5 16.5" />
                    </svg>
                    <span>IDE</span>
                </a>
                <button
                    wire:click="deleteTask"
                    wire:confirm="Are you sure you want to delete this chat and all its messages? This cannot be undone."
                    class="chat-header-action-btn chat-header-action-btn-danger"
                >
                    <x-heroicon-o-trash class="chat-header-action-icon" />
                    <span>Delete Chat</span>
                </button>
            </div>
        @endif
    </div>

    {{-- Chat Area --}}
    <div class="chat-area"
        x-data="{
            polling: @entangle('waitingForResponse').live,
            isNearBottom: true,
            scrollThreshold: 150,
            pendingScroll: null,
            cacheKey() {
                return `cr:chat-cache:${'{{ $task->uuid }}'}`;
            },
            scrollKey() {
                return `cr:chat-scroll:${'{{ $task->uuid }}'}`;
            },
            loadCachedMessages() {
                try {
                    const raw = localStorage.getItem(this.cacheKey());
                    if (!raw) return [];
                    const data = JSON.parse(raw);
                    return Array.isArray(data) ? data : [];
                } catch {
                    return [];
                }
            },
            renderCachedMessages() {
                const container = this.$refs.cachedHistory;
                if (!container) return;

                const cached = this.loadCachedMessages();
                if (!cached.length) return;

                // Minimal HTML renderer: fast preview while Livewire loads the real messages.
                // NOTE: This code lives inside an x-data HTML attribute (double-quoted).
                // We use the browser to escape HTML instead of manual entity replacement,
                // because entity literals like &#39; get decoded by the HTML parser before
                // Alpine evaluates the JS, breaking the expression.
                const _esc = document.createElement('span');
                const escapeHtml = (s) => {
                    _esc.textContent = s || '';
                    return _esc.innerHTML;
                };

                container.innerHTML = cached.map((m) => {
                    const isUser = m.role === 'user';
                    const outerClass = isUser ? 'chat-message chat-message-user' : 'chat-message chat-message-assistant';
                    const bubbleClass = isUser ? 'chat-bubble chat-bubble-user' : 'chat-bubble chat-bubble-assistant';
                    return `
                        <div class='${outerClass}'>
                            <div class='${bubbleClass}'>
                                <div class='chat-bubble-content'>${escapeHtml(m.text || '')}</div>
                            </div>
                        </div>
                    `;
                }).join('');
            },
            clearCachedRender() {
                const container = this.$refs.cachedHistory;
                if (!container) return;
                container.innerHTML = '';
            },
            extractMessagesForCache() {
                const nodes = Array.from(this.$refs.messages?.querySelectorAll('.chat-message') || []);
                const messages = [];
                for (const node of nodes) {
                    if (node.closest('.chat-cached-history')) continue;
                    const isUser = node.classList.contains('chat-message-user');
                    const contentEl = node.querySelector('.chat-bubble-content');
                    const text = (contentEl?.innerText || '').trim();
                    if (!text) continue;
                    messages.push({ role: isUser ? 'user' : 'assistant', text });
                }
                // Keep last 30 for size + speed.
                return messages.slice(-30);
            },
            saveMessagesToCache() {
                try {
                    const messages = this.extractMessagesForCache();
                    if (!messages.length) return;
                    localStorage.setItem(this.cacheKey(), JSON.stringify(messages));
                } catch {}
            },
            checkIfNearBottom() {
                const el = this.$refs.messages;
                this.isNearBottom = (el.scrollHeight - el.scrollTop - el.clientHeight) < this.scrollThreshold;
            },
            scrollToBottom() {
                this.$refs.messages.scrollTop = this.$refs.messages.scrollHeight;
            },
            init() {
                // Restore scroll position when navigating away and back in SPA mode.
                // Important: defer applying scrollTop until messages are actually rendered, otherwise
                // the browser clamps it to 0 and you end up stuck at the top.
                const readSavedScroll = () => {
                    try {
                        const raw = sessionStorage.getItem(this.scrollKey());
                        if (!raw) return false;
                        const data = JSON.parse(raw);
                        if (typeof data?.scrollTop !== 'number') return false;
                        this.pendingScroll = {
                            scrollTop: data.scrollTop,
                            isNearBottom: !!data?.isNearBottom,
                        };
                        return true;
                    } catch {
                        return false;
                    }
                };

                // Fast preview from local cache while Livewire fetches & renders.
                this.renderCachedMessages();

                if (!readSavedScroll()) {
                    this.scrollToBottom();
                }

                this.$refs.messages.addEventListener('scroll', () => this.checkIfNearBottom());
                const observer = new MutationObserver(() => {
                    this.$nextTick(() => {
                        if (this.isNearBottom) {
                            this.scrollToBottom();
                        }
                    });
                });
                observer.observe(this.$refs.messages, { childList: true, subtree: true });

                document.addEventListener('livewire:navigating', () => {
                    try {
                        sessionStorage.setItem(this.scrollKey(), JSON.stringify({
                            scrollTop: this.$refs.messages.scrollTop,
                            isNearBottom: this.isNearBottom,
                        }));
                    } catch {}
                    this.saveMessagesToCache();
                });

                document.addEventListener('messages-loaded', () => {
                    // Clear cached preview once real messages are in.
                    this.clearCachedRender();

                    // Apply pending scroll restore.
                    if (this.pendingScroll) {
                        const { scrollTop, isNearBottom } = this.pendingScroll;
                        this.pendingScroll = null;
                        this.isNearBottom = !!isNearBottom;
                        this.$nextTick(() => {
                            if (this.isNearBottom) {
                                this.scrollToBottom();
                                return;
                            }
                            this.$refs.messages.scrollTop = scrollTop;
                        });
                    } else {
                        // Default chat behavior: stay pinned to bottom when opening.
                        this.$nextTick(() => this.scrollToBottom());
                    }

                    // Refresh cache from the real DOM.
                    this.$nextTick(() => this.saveMessagesToCache());
                });
            }
        }"
    >
        {{-- Messages --}}
        <div
            class="chat-messages"
            x-ref="messages"
            wire:init="loadMessages"
            @if($this->shouldPoll) wire:poll.2s.visible="checkPolling" @endif
        >
            <div class="chat-cached-history" wire:ignore x-ref="cachedHistory"></div>

            {{-- Load earlier messages button --}}
            @if($this->messagesLoaded && $this->hasMoreMessages)
                <div class="chat-load-more">
                    <button
                        type="button"
                        wire:click="loadMoreMessages"
                        wire:loading.attr="disabled"
                        wire:target="loadMoreMessages"
                        class="chat-load-more-btn"
                    >
                        <span wire:loading.remove wire:target="loadMoreMessages">
                            ↑ Load {{ min($this->hiddenMessageCount, \App\Livewire\TaskChat::MESSAGES_PER_PAGE) }} earlier messages
                            <span class="chat-load-more-count">({{ $this->hiddenMessageCount }} hidden)</span>
                        </span>
                        <span wire:loading wire:target="loadMoreMessages">Loading...</span>
                    </button>
                </div>
            @endif

            @forelse($this->chatMessages as $index => $message)
                @php
                    $previousMessage = $index > 0 ? $this->chatMessages[$index - 1] : null;
                    $showDateSeparator = !$previousMessage || !$message->created_at->isSameDay($previousMessage->created_at);

                    // Use cached grouped blocks for performance
                    $groupedData = $message->getGroupedBlocks();
                    $firstBlockIsText = $groupedData['firstBlockIsText'];
                    $hasNoContentBlocks = $groupedData['hasNoContentBlocks'];
                    $shouldRenderFirstBubble = $message->isFromUser() || $firstBlockIsText || $hasNoContentBlocks;
                @endphp

                @if($showDateSeparator)
                    <div class="chat-date-separator">
                        <span class="chat-date-separator-text">
                            @if($message->created_at->isToday())
                                Today
                            @elseif($message->created_at->isYesterday())
                                Yesterday
                            @elseif($message->created_at->year === now()->year)
                                {{ $message->created_at->format('M j') }}
                            @else
                                {{ $message->created_at->format('M j, Y') }}
                            @endif
                        </span>
                    </div>
                @endif
                @if($shouldRenderFirstBubble)
                <div wire:key="message-{{ $message->id }}" class="chat-message {{ $message->isFromUser() ? 'chat-message-user' : 'chat-message-assistant' }}">
                    <div class="chat-bubble {{ $message->isFromUser() ? 'chat-bubble-user' : 'chat-bubble-assistant' }}">
                        @if($message->isFromUser())
                            @if($message->images && count($message->images) > 0)
                                <div class="chat-message-images">
                                    @foreach($message->images as $imageIndex => $image)
                                        <img
                                            src="{{ $image['data'] }}"
                                            alt="{{ $image['name'] ?? 'Image' }}"
                                            class="chat-message-image-thumb"
                                            @click="$dispatch('open-image-modal', { src: '{{ $image['data'] }}', alt: '{{ $image['name'] ?? 'Image' }}' })"
                                        >
                                    @endforeach
                                </div>
                            @endif
                            @if($message->content)
                                <p style="white-space: pre-wrap; margin: 0;">{!! $message->linkifyContent() !!}</p>
                            @endif
                            <span class="chat-message-time chat-message-time-user">{{ $message->created_at->timezone(config('app.timezone'))->format('H:i') }}</span>
                        @else
                            @if($firstBlockIsText)
                                {{-- Render first text block (using cached markdown) --}}
                                <div class="chat-bubble-content">
                                    {!! $message->getFirstTextBlockHtml() !!}
                                </div>

                                <div class="chat-message-meta">
                                    @php
                                        $firstBlockTimestamp = isset($message->content_blocks[0]['timestamp'])
                                            ? \Carbon\Carbon::parse($message->content_blocks[0]['timestamp'])->timezone(config('app.timezone'))->format('H:i')
                                            : $message->created_at->timezone(config('app.timezone'))->format('H:i');
                                    @endphp
                                    <span class="chat-message-time">{{ $firstBlockTimestamp }}</span>
                                    {{-- Only show tokens on the last bubble --}}
                                    @if(count($message->content_blocks) <= 1 && ($message->tokens_in || $message->tokens_out))
                                        <span class="chat-tokens">
                                            {{ number_format($message->tokens_in ?? 0) }} in /
                                            {{ number_format($message->tokens_out ?? 0) }} out
                                            @if($message->cost_usd)
                                                (${{ number_format($message->cost_usd, 4) }})
                                            @endif
                                        </span>
                                    @endif
                                </div>

                                {{-- Inline Compact Action for "Prompt is too long" messages --}}
                                @if(trim($message->content ?? '') === 'Prompt is too long')
                                    <button
                                        type="button"
                                        wire:click="sendCompactCommand"
                                        class="chat-compact-action"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 9V4.5M9 9H4.5M9 9L3.75 3.75M9 15v4.5M9 15H4.5M9 15l-5.25 5.25M15 9h4.5M15 9V4.5M15 9l5.25-5.25M15 15h4.5M15 15v4.5m0-4.5l5.25 5.25" />
                                        </svg>
                                        Click to compact context
                                    </button>
                                @endif
                            @elseif($hasNoContentBlocks)
                                {{-- Fallback for old messages without content_blocks --}}
                                <div class="chat-bubble-content">
                                    {!! Str::markdown($message->content ?? '') !!}
                                </div>

                                @if($message->tool_calls && count($message->tool_calls) > 0)
                                    <div x-data="{ showTools: false }" class="chat-tool-calls-container">
                                        <button
                                            type="button"
                                            @click="showTools = !showTools"
                                            class="chat-tool-toggle"
                                        >
                                            <span x-text="showTools ? '▼' : '▶'" class="chat-tool-toggle-icon"></span>
                                            <span>{{ count($message->tool_calls) }} tool {{ Str::plural('call', count($message->tool_calls)) }}</span>
                                        </button>
                                        <div x-show="showTools" x-collapse class="chat-tool-calls">
                                            @foreach($message->tool_calls as $tool)
                                                <details class="chat-tool-call">
                                                    <summary>{{ $tool['name'] ?? 'Tool' }}</summary>
                                                    <pre>{{ json_encode($tool['input'] ?? [], JSON_PRETTY_PRINT) }}</pre>
                                                </details>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                <div class="chat-message-meta">
                                    <span class="chat-message-time">{{ $message->created_at->timezone(config('app.timezone'))->format('H:i') }}</span>
                                    @if($message->tokens_in || $message->tokens_out)
                                        <span class="chat-tokens">
                                            {{ number_format($message->tokens_in ?? 0) }} in /
                                            {{ number_format($message->tokens_out ?? 0) }} out
                                            @if($message->cost_usd)
                                                (${{ number_format($message->cost_usd, 4) }})
                                            @endif
                                        </span>
                                    @endif
                                </div>

                                {{-- Inline Compact Action for "Prompt is too long" messages --}}
                                @if(trim($message->content ?? '') === 'Prompt is too long')
                                    <button
                                        type="button"
                                        wire:click="sendCompactCommand"
                                        class="chat-compact-action"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 9V4.5M9 9H4.5M9 9L3.75 3.75M9 15v4.5M9 15H4.5M9 15l-5.25 5.25M15 9h4.5M15 9V4.5M15 9l5.25-5.25M15 15h4.5M15 15v4.5m0-4.5l5.25 5.25" />
                                        </svg>
                                        Click to compact context
                                    </button>
                                @endif
                            @endif
                            {{-- If first block is tool_use, skip the initial bubble - it will be rendered below with grouped blocks --}}
                        @endif
                    </div>
                </div>
                @endif
                {{-- Render remaining content blocks with tool calls grouped (using cached data) --}}
                @if($message->isFromAssistant() && !$hasNoContentBlocks)
                    @php
                        // Check if message is expanded (show all blocks)
                        $isExpanded = isset($this->expandedMessageIds[$message->id]);

                        // Use all blocks if expanded, otherwise use truncated version
                        if ($isExpanded && ($groupedData['truncated'] ?? false)) {
                            $allData = $message->getAllGroupedBlocks();
                            $groupedBlocks = $allData['groupedBlocks'];
                            $truncated = false;
                            $totalBlockCount = $allData['totalBlockCount'];
                        } else {
                            $groupedBlocks = $groupedData['groupedBlocks'];
                            $truncated = $groupedData['truncated'] ?? false;
                            $totalBlockCount = $groupedData['totalBlockCount'] ?? 0;
                        }
                    @endphp

                    {{-- Show truncation notice if blocks were limited (with expand option) --}}
                    @if($truncated)
                        <div wire:key="message-{{ $message->id }}-truncated" class="chat-message chat-message-assistant">
                            <div class="chat-bubble chat-bubble-tool" style="background: rgb(254 243 199); border-color: rgb(253 230 138);">
                                <span style="color: rgb(146 64 14); font-size: 0.75rem;">
                                    ⚠️ Showing last {{ count($groupedBlocks) }} of {{ $totalBlockCount }} blocks ({{ $totalBlockCount - count($groupedBlocks) }} hidden for performance)
                                    <button
                                        wire:click="toggleExpandMessage({{ $message->id }})"
                                        class="ml-2 underline hover:no-underline cursor-pointer"
                                        style="color: rgb(146 64 14);"
                                    >
                                        Show all blocks
                                    </button>
                                </span>
                            </div>
                        </div>
                    @elseif($isExpanded && $totalBlockCount > \App\Models\Message::MAX_RENDERED_BLOCKS)
                        {{-- Show collapse option when expanded --}}
                        <div wire:key="message-{{ $message->id }}-expanded" class="chat-message chat-message-assistant">
                            <div class="chat-bubble chat-bubble-tool" style="background: rgb(220 252 231); border-color: rgb(187 247 208);">
                                <span style="color: rgb(22 101 52); font-size: 0.75rem;">
                                    Showing all {{ $totalBlockCount }} blocks
                                    <button
                                        wire:click="toggleExpandMessage({{ $message->id }})"
                                        class="ml-2 underline hover:no-underline cursor-pointer"
                                        style="color: rgb(22 101 52);"
                                    >
                                        Collapse
                                    </button>
                                </span>
                            </div>
                        </div>
                    @endif

                    @if(count($groupedBlocks) > 0)
                    @foreach($groupedBlocks as $blockIndex => $block)
                        @php
                            $isLastBlock = $blockIndex === count($groupedBlocks) - 1;
                            // Get timestamp from block if available, otherwise fall back to message created_at
                            $blockTimestamp = isset($block['timestamp'])
                                ? \Carbon\Carbon::parse($block['timestamp'])->timezone(config('app.timezone'))->format('H:i')
                                : (isset($block['tools'][0]['timestamp'])
                                    ? \Carbon\Carbon::parse($block['tools'][0]['timestamp'])->timezone(config('app.timezone'))->format('H:i')
                                    : $message->created_at->timezone(config('app.timezone'))->format('H:i'));
                        @endphp
                        @if(($block['type'] ?? '') === 'text' && !empty($block['text']))
                            <div wire:key="message-{{ $message->id }}-grouped-{{ $blockIndex }}"
                                 class="chat-message chat-message-assistant">
                                <div class="chat-bubble chat-bubble-assistant">
                                    <div class="chat-bubble-content">
                                        {!! $message->renderMarkdown($block['text']) !!}
                                    </div>
                                    <div class="chat-message-meta">
                                        <span class="chat-message-time">{{ $blockTimestamp }}</span>
                                        @if($isLastBlock && ($message->tokens_in || $message->tokens_out))
                                            <span class="chat-tokens">
                                                {{ number_format($message->tokens_in ?? 0) }} in /
                                                {{ number_format($message->tokens_out ?? 0) }} out
                                                @if($message->cost_usd)
                                                    (${{ number_format($message->cost_usd, 4) }})
                                                @endif
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @elseif(($block['type'] ?? '') === 'collapsed_text')
                            {{-- Collapsed short text blocks (stuck loop display) --}}
                            <div wire:key="message-{{ $message->id }}-grouped-{{ $blockIndex }}"
                                 class="chat-message chat-message-assistant">
                                <div class="chat-bubble chat-bubble-collapsed">
                                    <span class="chat-collapsed-text">{{ $block['text'] }}</span>
                                    <span class="chat-collapsed-count">{{ $block['count'] }}</span>
                                </div>
                            </div>
                        @elseif(($block['type'] ?? '') === 'tool_group')
                            <div wire:key="message-{{ $message->id }}-grouped-{{ $blockIndex }}"
                                 class="chat-message chat-message-assistant">
                                <div class="chat-bubble chat-bubble-tool">
                                    @if(count($block['tools']) === 1)
                                        @php
                                            $toolCommand = $block['tools'][0]['tool']['input']['command'] ?? null;
                                        @endphp
                                        <div class="chat-tool-use">
                                            <span class="chat-tool-use-icon">⚙</span>
                                            <span
                                                class="chat-tool-use-name"
                                                @if($toolCommand)
                                                    title="{{ $toolCommand }}"
                                                @endif
                                            >
                                                {{ $block['tools'][0]['tool']['name'] ?? 'Tool' }}
                                            </span>
                                        </div>
                                    @else
                                        <div x-data="{ showTools: false }" class="chat-tool-calls-container">
                                            <button
                                                type="button"
                                                @click="showTools = !showTools"
                                                class="chat-tool-toggle"
                                            >
                                                <span x-text="showTools ? '▼' : '▶'" class="chat-tool-toggle-icon"></span>
                                                <span>{{ count($block['tools']) }} tool {{ Str::plural('call', count($block['tools'])) }}</span>
                                            </button>
                                            <div x-show="showTools" x-collapse class="chat-tool-calls">
                                            @foreach($block['tools'] as $tool)
                                                @php
                                                    $toolCommand = $tool['tool']['input']['command'] ?? null;
                                                @endphp
                                                <div class="chat-tool-use" style="margin-bottom: 0.25rem;">
                                                    <span class="chat-tool-use-icon">⚙</span>
                                                    <span
                                                        class="chat-tool-use-name"
                                                        @if($toolCommand)
                                                            title="{{ $toolCommand }}"
                                                        @endif
                                                    >
                                                        {{ $tool['tool']['name'] ?? 'Tool' }}
                                                    </span>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                    @endif
                                    <div class="chat-message-meta">
                                        <span class="chat-message-time">{{ $blockTimestamp }}</span>
                                        @if($isLastBlock && ($message->tokens_in || $message->tokens_out))
                                            <span class="chat-tokens">
                                                {{ number_format($message->tokens_in ?? 0) }} in /
                                                {{ number_format($message->tokens_out ?? 0) }} out
                                                @if($message->cost_usd)
                                                    (${{ number_format($message->cost_usd, 4) }})
                                                @endif
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @elseif(($block['type'] ?? '') === 'ask_user_question')
                            @php
                                $toolId = $block['tool']['id'] ?? '';
                                $questions = $block['tool']['input']['questions'] ?? [];
                                // Check if this question has already been answered
                                $existingResponse = $this->getQuestionResponse($message->id, $toolId);
                            @endphp
                            <div wire:key="message-{{ $message->id }}-question-{{ $blockIndex }}"
                                 class="chat-message chat-message-assistant"
                                 x-data="{
                                    responses: @js($existingResponse ?? []),
                                    submitted: {{ $existingResponse ? 'true' : 'false' }},
                                    submitting: false,
                                    otherText: {},
                                    multiSelectValues: {},
                                    init() {
                                        // Initialize multiSelectValues for multi-select questions
                                        @foreach($questions as $qIndex => $question)
                                            @if($question['multiSelect'] ?? false)
                                                this.multiSelectValues[{{ $qIndex }}] = [];
                                            @endif
                                        @endforeach
                                    },
                                    toggleMultiSelect(qIndex, value) {
                                        if (!this.multiSelectValues[qIndex]) {
                                            this.multiSelectValues[qIndex] = [];
                                        }
                                        const idx = this.multiSelectValues[qIndex].indexOf(value);
                                        if (idx === -1) {
                                            this.multiSelectValues[qIndex].push(value);
                                        } else {
                                            this.multiSelectValues[qIndex].splice(idx, 1);
                                        }
                                        this.responses[qIndex] = this.multiSelectValues[qIndex].join(', ');
                                    },
                                    isMultiSelected(qIndex, value) {
                                        return this.multiSelectValues[qIndex]?.includes(value) ?? false;
                                    },
                                    submit() {
                                        this.submitting = true;
                                        // Check for 'Other' responses and replace with custom text
                                        const finalResponses = {};
                                        Object.keys(this.responses).forEach(key => {
                                            let value = this.responses[key];
                                            // Handle 'Other' option
                                            if (value === '__other__' || (typeof value === 'string' && value.includes('__other__'))) {
                                                value = this.otherText[key] || 'Other';
                                            }
                                            finalResponses[key] = value;
                                        });
                                        $wire.submitQuestionResponse({{ $message->id }}, '{{ $toolId }}', finalResponses)
                                            .then(() => {
                                                this.submitted = true;
                                                this.submitting = false;
                                            })
                                            .catch(() => {
                                                this.submitting = false;
                                            });
                                    }
                                 }">
                                <div class="chat-bubble chat-bubble-question">
                                    <div class="chat-question-header">
                                        <span class="chat-question-icon">❓</span>
                                        <span class="chat-question-title">{{ $this->providerLabel }} needs your input</span>
                                    </div>

                                    <div class="chat-questions-list">
                                        @foreach($questions as $qIndex => $question)
                                            <div class="chat-question-item">
                                                @if(!empty($question['header']))
                                                    <span class="chat-question-chip">{{ $question['header'] }}</span>
                                                @endif
                                                <p class="chat-question-text">{{ $question['question'] ?? 'Please select an option:' }}</p>

                                                <div class="chat-question-options">
                                                    @foreach($question['options'] ?? [] as $oIndex => $option)
                                                        @php $optionLabel = $option['label'] ?? $option['description'] ?? "Option " . ($oIndex + 1); @endphp
                                                        @if($question['multiSelect'] ?? false)
                                                            {{-- Multi-select: checkboxes --}}
                                                            <label class="chat-question-option"
                                                                   :class="{ 'selected': isMultiSelected({{ $qIndex }}, '{{ addslashes($optionLabel) }}'), 'disabled': submitted }">
                                                                <input type="checkbox"
                                                                       :disabled="submitted"
                                                                       @change="toggleMultiSelect({{ $qIndex }}, '{{ addslashes($optionLabel) }}')"
                                                                       :checked="isMultiSelected({{ $qIndex }}, '{{ addslashes($optionLabel) }}')"
                                                                       class="sr-only">
                                                                <span class="chat-option-label">{{ $optionLabel }}</span>
                                                                @if(!empty($option['description']) && isset($option['label']))
                                                                    <span class="chat-option-desc">{{ $option['description'] }}</span>
                                                                @endif
                                                            </label>
                                                        @else
                                                            {{-- Single select: radio buttons --}}
                                                            <label class="chat-question-option"
                                                                   :class="{ 'selected': responses[{{ $qIndex }}] === '{{ addslashes($optionLabel) }}', 'disabled': submitted }">
                                                                <input type="radio"
                                                                       name="question-{{ $message->id }}-{{ $qIndex }}"
                                                                       value="{{ $optionLabel }}"
                                                                       :disabled="submitted"
                                                                       x-model="responses[{{ $qIndex }}]"
                                                                       class="sr-only">
                                                                <span class="chat-option-label">{{ $optionLabel }}</span>
                                                                @if(!empty($option['description']) && isset($option['label']))
                                                                    <span class="chat-option-desc">{{ $option['description'] }}</span>
                                                                @endif
                                                            </label>
                                                        @endif
                                                    @endforeach

                                                    {{-- "Other" option with text input --}}
                                                    <label class="chat-question-option chat-question-option-other"
                                                           :class="{ 'selected': responses[{{ $qIndex }}] === '__other__', 'disabled': submitted }">
                                                        <input type="radio"
                                                               name="question-{{ $message->id }}-{{ $qIndex }}"
                                                               value="__other__"
                                                               :disabled="submitted"
                                                               x-model="responses[{{ $qIndex }}]"
                                                               class="sr-only">
                                                        <span class="chat-option-label">Other</span>
                                                    </label>
                                                    <div x-show="responses[{{ $qIndex }}] === '__other__'" x-collapse>
                                                        <input type="text"
                                                               x-model="otherText[{{ $qIndex }}]"
                                                               :disabled="submitted"
                                                               placeholder="Enter your response..."
                                                               class="chat-question-other-input">
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>

                                    <div class="chat-question-actions">
                                        <button type="button"
                                                @click="submit()"
                                                :disabled="submitting || submitted || Object.keys(responses).length !== {{ count($questions) }}"
                                                class="chat-question-submit"
                                                :class="{ 'submitted': submitted }">
                                            <span x-show="!submitting && !submitted">Submit Response</span>
                                            <span x-show="submitting">Sending...</span>
                                            <span x-show="submitted">✓ Response Sent</span>
                                        </button>
                                    </div>

                                    <div class="chat-message-meta">
                                        <span class="chat-message-time">{{ $blockTimestamp }}</span>
                                    </div>
                                </div>
                            </div>
                        @endif
                    @endforeach
                    @endif
                @endif
            @empty
                @if(! $this->messagesLoaded && $this->lastMessageCount > 0)
                    <div class="chat-empty">
                        <p>Loading messages…</p>
                    </div>
                @else
                    <div class="chat-empty">
                        <p>Start a conversation with {{ $this->providerLabel }}</p>
                    </div>
                @endif
            @endforelse

            @if($this->isRunning || $this->waitingForResponse || $this->hasActiveSubagents)
            @php
                $latestAssistantMessage = $this->chatMessages
                    ->where('role', \App\Enums\MessageRole::Assistant)
                    ->last();
                $activityEvents = $latestAssistantMessage?->getActivityEvents() ?? [];
                $showActivity = $this->currentProvider?->isCodex() && count($activityEvents) > 0;
            @endphp
            <div class="chat-thinking">
                <div class="chat-thinking-bubble">
                    <div class="chat-thinking-content">
                        <div class="chat-thinking-dot"></div>
                        <span class="chat-thinking-text">
                            @if($this->hasActiveSubagents && !$this->isRunning)
                                Subagents working...
                            @else
                                {{ $this->providerLabel }} is thinking...
                            @endif
                        </span>
                    </div>
                    @if($showActivity)
                        <div x-data="{ showActivity: false }" class="chat-activity">
                            <button
                                type="button"
                                @click="showActivity = !showActivity"
                                class="chat-activity-toggle"
                            >
                                <span x-text="showActivity ? '▼' : '▶'"></span>
                                <span>Activity ({{ count($activityEvents) }})</span>
                            </button>
                            <div x-show="showActivity" x-collapse class="chat-activity-log">
                                @foreach($activityEvents as $event)
                                    <div class="chat-activity-line">{{ $event }}</div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    <button
                        type="button"
                        wire:click="stopRunning"
                        wire:loading.attr="disabled"
                        class="chat-stop-btn"
                        title="Stop {{ $this->providerLabel }}"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width: 1rem; height: 1rem;">
                            <path fill-rule="evenodd" d="M4.5 7.5a3 3 0 013-3h9a3 3 0 013 3v9a3 3 0 01-3 3h-9a3 3 0 01-3-3v-9z" clip-rule="evenodd" />
                        </svg>
                        <span wire:loading.remove wire:target="stopRunning">Stop</span>
                        <span wire:loading wire:target="stopRunning">Stopping...</span>
                    </button>
                </div>
            </div>
            @endif
        </div>

        {{-- Queued Messages (stacked above input) --}}
        @if($this->queuedMessages->isNotEmpty())
            <div class="chat-queued-messages">
                @foreach($this->queuedMessages as $queuedMessage)
                    <div wire:key="queued-{{ $queuedMessage->id }}" class="chat-queued-message">
                        <div class="chat-queued-message-content">
                            @if($queuedMessage->images && count($queuedMessage->images) > 0)
                                <span class="chat-queued-message-images">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1rem; height: 1rem;">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                                    </svg>
                                    {{ count($queuedMessage->images) }}
                                </span>
                            @endif
                            @if($queuedMessage->content)
                                <span class="chat-queued-message-text">{{ Str::limit($queuedMessage->content, 100) }}</span>
                            @endif
                        </div>
                        <button
                            type="button"
                            wire:click="deleteQueuedMessage({{ $queuedMessage->id }})"
                            class="chat-queued-message-remove"
                            title="Remove from queue"
                        >&times;</button>
                    </div>
                @endforeach
                <div class="chat-queued-label">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 0.875rem; height: 0.875rem;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                    {{ $this->queuedMessages->count() }} {{ Str::plural('message', $this->queuedMessages->count()) }} queued - will send when {{ $this->providerLabel }} finishes
                </div>
            </div>
        @endif

{{-- Input --}}
        <div class="chat-input-area"
            x-data="{
                prompt: '',
                images: @entangle('images'),
                taskUuid: '{{ $this->task->uuid }}',
                isRecording: false,
                isTranscribing: false,
                recordingSeconds: 0,
                voiceError: '',
                recordingMode: null, // 'media' | 'wav'
                recorder: null,
                recordStream: null,
                recordTimeoutId: null,
                recordIntervalId: null,
                maxRecordMs: 5 * 60 * 1000,
                // WAV recorder state (used when MediaRecorder can't produce a provider-supported format)
                wavContext: null,
                wavSource: null,
                wavProcessor: null,
                wavBuffers: [],
                get canSend() {
                    if (this.isRecording || this.isTranscribing) return false;
                    return this.prompt.trim().length > 0 || this.images.length > 0;
                },
                get recordingLabel() {
                    const m = Math.floor(this.recordingSeconds / 60).toString();
                    const s = (this.recordingSeconds % 60).toString().padStart(2, '0');
                    return `${m}:${s}`;
                },
                draftKey() {
                    return `cr:chat-draft:${this.taskUuid}`;
                },
                saveDraft() {
                    try {
                        sessionStorage.setItem(this.draftKey(), JSON.stringify({
                            prompt: this.prompt || '',
                        }));
                    } catch {}
                },
                restoreDraft() {
                    try {
                        const raw = sessionStorage.getItem(this.draftKey());
                        if (!raw) return;
                        const data = JSON.parse(raw);
                        if (typeof data?.prompt === 'string' && data.prompt.trim().length > 0 && this.prompt.trim().length === 0) {
                            this.prompt = data.prompt;
                        }
                    } catch {}
                },
                clearDraft() {
                    try { sessionStorage.removeItem(this.draftKey()); } catch {}
                },
                init() {
                    this.restoreDraft();

                    // Listen for snippet insertions from Livewire
                    Livewire.on('insert-snippet', (data) => {
                        if (this.prompt.length > 0) {
                            this.prompt += '\n\n';
                        }
                        this.prompt += data.content;
                        // Focus the textarea
                        this.$refs.promptInput?.focus();
                        this.saveDraft();
                    });

                    // Persist prompt draft when navigating between pages in SPA mode.
                    const save = () => this.saveDraft();
                    document.addEventListener('livewire:navigating', save);
                    window.addEventListener('beforeunload', save);
                },
                submit() {
                    if (!this.canSend) return;
                    // Sync prompt to Livewire and send
                    $wire.prompt = this.prompt;
                    $wire.sendMessage().then(() => {
                        this.prompt = '';
                        this.clearDraft();
                    });
                },
                handlePaste(e) {
                    const items = e.clipboardData?.items;
                    if (!items) return;

                    for (const item of items) {
                        if (item.type.startsWith('image/')) {
                            e.preventDefault();
                            const file = item.getAsFile();
                            if (file) {
                                this.processFile(file);
                            }
                        }
                    }
                },
                handleFileSelect(e) {
                    const files = e.target.files;
                    if (!files) return;

                    for (const file of files) {
                        if (file.type.startsWith('image/')) {
                            this.processFile(file);
                        }
                    }
                    // Reset input so same file can be selected again
                    e.target.value = '';
                },
                processFile(file) {
                    const reader = new FileReader();
                    reader.onload = (event) => {
                        this.images.push({
                            data: event.target.result,
                            name: file.name || 'image.png'
                        });
                    };
                    reader.readAsDataURL(file);
                },
                removeImage(index) {
                    this.images.splice(index, 1);
                },
                openFilePicker() {
                    this.$refs.fileInput.click();
                },
                openAudioPicker() {
                    this.$refs.audioInput?.click();
                },
                pickAudioMimeType() {
                    const candidates = [
                        'audio/mp4', // iOS-friendly (AAC in MP4 container)
                        'audio/webm;codecs=opus',
                        'audio/webm',
                        'audio/ogg;codecs=opus',
                        'audio/ogg',
                    ];
                    for (const mime of candidates) {
                        if (window.MediaRecorder && MediaRecorder.isTypeSupported?.(mime)) return mime;
                    }
                    return '';
                },
                suggestedVoiceFilename(mimeType) {
                    const mime = (mimeType || '').toLowerCase();
                    if (mime.includes('mp4') || mime.includes('m4a')) return 'voice-message.m4a';
                    if (mime.includes('mpeg') || mime.includes('mp3')) return 'voice-message.mp3';
                    if (mime.includes('wav')) return 'voice-message.wav';
                    if (mime.includes('ogg')) return 'voice-message.ogg';
                    if (mime.includes('webm')) return 'voice-message.webm';
                    return 'voice-message.webm';
                },
                shouldUseWavRecorder(mimeType) {
                    // OpenRouter's OpenAI audio-chat models accept `input_audio.format` values like `wav` (and `mp3`).
                    // Browsers commonly output mp4/webm/ogg via MediaRecorder; without server-side transcoding, those
                    // will fail. Use the WAV recorder by default for portability.
                    const mime = (mimeType || '').toLowerCase();
                    if (!mime) return true;
                    if (mime.includes('wav') || mime.includes('mpeg') || mime.includes('mp3')) return false;
                    return true;
                },
                async toggleRecording() {
                    if (this.isTranscribing) return;
                    if (this.isRecording) {
                        await this.stopRecording();
                        return;
                    }
                    await this.startRecording();
                },
                async startRecording() {
                    this.voiceError = '';
                    if (!navigator.mediaDevices?.getUserMedia) {
                        this.voiceError = 'Voice recording is not supported on this device.';
                        // Fallback: let user select an audio file (may open mic on mobile).
                        this.openAudioPicker();
                        return;
                    }

                    let stream;
                    try {
                        stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                    } catch (e) {
                        this.voiceError = 'Microphone permission denied.';
                        return;
                    }

                    this.recordStream = stream;
                    const hasMediaRecorder = !!window.MediaRecorder;
                    const mimeType = hasMediaRecorder ? this.pickAudioMimeType() : '';
                    if (!hasMediaRecorder || this.shouldUseWavRecorder(mimeType)) {
                        await this.startWavRecording(stream);
                        return;
                    }

                    const chunks = [];

                    try {
                        this.recorder = mimeType ? new MediaRecorder(stream, { mimeType }) : new MediaRecorder(stream);
                    } catch (e) {
                        this.voiceError = 'Unable to start recording on this device.';
                        stream.getTracks().forEach((t) => t.stop());
                        this.recordStream = null;
                        return;
                    }

                    this.recordingSeconds = 0;
                    this.isRecording = true;
                    this.recordingMode = 'media';

                    this.recorder.addEventListener('dataavailable', (event) => {
                        if (event.data && event.data.size > 0) chunks.push(event.data);
                    });

                    this.recorder.addEventListener('stop', async () => {
                        const blob = new Blob(chunks, { type: this.recorder?.mimeType || 'audio/webm' });
                        this.cleanupRecording();
                        await this.transcribeAndSend(blob);
                    }, { once: true });

                    this.recorder.start();

                    this.recordIntervalId = setInterval(() => {
                        this.recordingSeconds += 1;
                    }, 1000);

                    this.recordTimeoutId = setTimeout(() => {
                        this.stopRecording();
                    }, this.maxRecordMs);
                },
                async stopRecording() {
                    if (this.recordingMode === 'wav') {
                        await this.stopWavRecording();
                        return;
                    }
                    if (!this.recorder) return;
                    try { this.recorder.stop(); } catch {}
                },
                async startWavRecording(stream) {
                    const AudioContext = window.AudioContext || window.webkitAudioContext;
                    if (!AudioContext) {
                        this.voiceError = 'Voice recording is not supported on this device.';
                        stream.getTracks().forEach((t) => t.stop());
                        this.recordStream = null;
                        return;
                    }

                    this.recordingSeconds = 0;
                    this.isRecording = true;
                    this.recordingMode = 'wav';
                    this.wavBuffers = [];

                    // Use a lower sample rate to keep WAV size reasonable; browsers may ignore the hint.
                    const ctx = new AudioContext({ sampleRate: 16000 });
                    const source = ctx.createMediaStreamSource(stream);
                    const processor = ctx.createScriptProcessor(4096, 1, 1);

                    processor.onaudioprocess = (e) => {
                        const input = e.inputBuffer.getChannelData(0);
                        this.wavBuffers.push(new Float32Array(input));
                        // Prevent feedback/echo.
                        const out = e.outputBuffer.getChannelData(0);
                        out.fill(0);
                    };

                    source.connect(processor);
                    processor.connect(ctx.destination);

                    this.wavContext = ctx;
                    this.wavSource = source;
                    this.wavProcessor = processor;
                    try { await ctx.resume(); } catch {}

                    this.recordIntervalId = setInterval(() => {
                        this.recordingSeconds += 1;
                    }, 1000);

                    this.recordTimeoutId = setTimeout(() => {
                        this.stopRecording();
                    }, this.maxRecordMs);
                },
                encodeWavBlob(float32Chunks, sampleRate) {
                    let length = 0;
                    for (const c of float32Chunks) length += c.length;

                    const buffer = new ArrayBuffer(44 + length * 2);
                    const view = new DataView(buffer);

                    const writeStr = (offset, str) => {
                        for (let i = 0; i < str.length; i++) view.setUint8(offset + i, str.charCodeAt(i));
                    };

                    // RIFF header
                    writeStr(0, 'RIFF');
                    view.setUint32(4, 36 + length * 2, true);
                    writeStr(8, 'WAVE');

                    // fmt chunk
                    writeStr(12, 'fmt ');
                    view.setUint32(16, 16, true); // PCM
                    view.setUint16(20, 1, true); // format
                    view.setUint16(22, 1, true); // channels
                    view.setUint32(24, sampleRate, true);
                    view.setUint32(28, sampleRate * 2, true); // byte rate
                    view.setUint16(32, 2, true); // block align
                    view.setUint16(34, 16, true); // bits

                    // data chunk
                    writeStr(36, 'data');
                    view.setUint32(40, length * 2, true);

                    let offset = 44;
                    for (const chunk of float32Chunks) {
                        for (let i = 0; i < chunk.length; i++) {
                            const s = Math.max(-1, Math.min(1, chunk[i]));
                            view.setInt16(offset, s < 0 ? s * 0x8000 : s * 0x7fff, true);
                            offset += 2;
                        }
                    }

                    return new Blob([buffer], { type: 'audio/wav' });
                },
                async stopWavRecording() {
                    const ctx = this.wavContext;
                    const processor = this.wavProcessor;
                    const source = this.wavSource;

                    // Snapshot buffers before cleanup.
                    const buffers = this.wavBuffers || [];
                    const sampleRate = ctx?.sampleRate || 16000;

                    try { processor?.disconnect(); } catch {}
                    try { source?.disconnect(); } catch {}
                    if (processor) processor.onaudioprocess = null;
                    try { await ctx?.close(); } catch {}

                    this.wavContext = null;
                    this.wavSource = null;
                    this.wavProcessor = null;

                    const blob = this.encodeWavBlob(buffers, sampleRate);
                    this.cleanupRecording();
                    await this.transcribeAndSend(blob);
                },
                cleanupRecording() {
                    this.isRecording = false;
                    this.recordingMode = null;

                    if (this.recordTimeoutId) clearTimeout(this.recordTimeoutId);
                    if (this.recordIntervalId) clearInterval(this.recordIntervalId);
                    this.recordTimeoutId = null;
                    this.recordIntervalId = null;

                    if (this.recordStream) {
                        this.recordStream.getTracks().forEach((t) => t.stop());
                    }
                    this.recordStream = null;
                    this.recorder = null;
                },
                async handleAudioSelect(e) {
                    const file = e.target.files?.[0];
                    e.target.value = '';
                    if (!file) return;
                    this.voiceError = '';
                    await this.transcribeAndSend(file);
                },
                getCsrfToken() {
                    return document.querySelector('meta[name=csrf-token]')?.content || '';
                },
                async transcribeAndSend(audioBlobOrFile) {
                    this.isTranscribing = true;
                    this.voiceError = '';

                    try {
                        const form = new FormData();
                        // Important: make the filename extension match the blob's mimetype.
                        // iOS often records `audio/mp4`, and a misleading `.webm` extension can
                        // cause the backend/provider to mis-detect the format.
                        const filename = audioBlobOrFile.name || this.suggestedVoiceFilename(audioBlobOrFile.type);
                        form.append('audio', audioBlobOrFile, filename);

                        const res = await fetch(`/api/tasks/${this.taskUuid}/voice-transcribe`, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': this.getCsrfToken(),
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json',
                            },
                            credentials: 'same-origin',
                            body: form,
                        });

                        const requestIdHeader = res.headers.get('x-voice-request-id') || '';
                        const cfRay = res.headers.get('cf-ray') || '';
                        const serverHeader = res.headers.get('server') || '';
                        const contentType = (res.headers.get('content-type') || '').toLowerCase();
                        const resClone = res.clone();
                        const json = await resClone.json().catch(() => null);
                        const rawText = json === null ? await res.text().catch(() => '') : '';

                        if (!res.ok) {
                            const providerMsg =
                                json?.error?.error?.message ||
                                json?.error?.message ||
                                json?.message ||
                                '';

                            const reqId = requestIdHeader || json?.request_id || '';
                            const reqIdSuffix = reqId ? ` (request ${reqId})` : '';

                            // Avoid dumping HTML error pages into the UI; surface headers instead.
                            const isHtml = contentType.includes('text/html') || rawText.trim().toLowerCase().startsWith('<!doctype html');
                            const infraSuffixParts = [];
                            if (serverHeader) infraSuffixParts.push(serverHeader);
                            if (cfRay) infraSuffixParts.push(`cf-ray ${cfRay}`);
                            const infraSuffix = infraSuffixParts.length ? ` (${infraSuffixParts.join(', ')})` : '';

                            if (isHtml) {
                                this.voiceError = `Transcription failed (HTTP ${res.status})${reqIdSuffix}${infraSuffix}.`;
                                return;
                            }

                            this.voiceError = (providerMsg ? `${providerMsg}${reqIdSuffix}` : `Transcription failed (HTTP ${res.status})${reqIdSuffix}${infraSuffix}.`);
                            return;
                        }

                        const transcript = (json?.transcript || '').trim();
                        if (!transcript) {
                            const reqId = requestIdHeader || json?.request_id || '';
                            this.voiceError = reqId ? `Transcription returned empty text (request ${reqId}).` : 'Transcription returned empty text.';
                            return;
                        }

                        // Auto-send: insert and submit.
                        this.prompt = transcript;
                        await this.$nextTick();
                        this.submit();
                    } catch (e) {
                        this.voiceError = 'Transcription failed due to a network error.';
                    } finally {
                        this.isTranscribing = false;
                    }
                }
            }"
        >
            {{-- Hidden file input for mobile attachment --}}
            <input
                type="file"
                x-ref="fileInput"
                @change="handleFileSelect($event)"
                accept="image/*"
                multiple
                class="chat-file-input"
            >

            {{-- Hidden file input fallback for audio selection --}}
            <input
                type="file"
                x-ref="audioInput"
                @change="handleAudioSelect($event)"
                accept="audio/*"
                class="chat-file-input"
            >

            <form @submit.prevent="submit()" class="chat-form">
                {{-- Attachment button (primarily for mobile) --}}
                <button
                    type="button"
                    @click="openFilePicker()"
                    class="chat-attach-btn"
                    title="Attach image"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z" />
                    </svg>
                </button>

                {{-- Voice message (tap to record / tap to stop) --}}
                <button
                    type="button"
                    @click="toggleRecording()"
                    class="chat-voice-btn"
                    :class="{ 'is-recording': isRecording, 'is-busy': isTranscribing }"
                    :disabled="isTranscribing"
                    :title="isRecording ? 'Stop recording' : 'Record voice message'"
                >
                    <template x-if="!isRecording && !isTranscribing">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M8.25 12a3.75 3.75 0 1 0 7.5 0V6a3.75 3.75 0 1 0-7.5 0v6Z" />
                            <path d="M6 10.5a.75.75 0 0 1 .75.75V12a5.25 5.25 0 0 0 10.5 0v-.75a.75.75 0 0 1 1.5 0V12a6.75 6.75 0 0 1-6 6.708V21a.75.75 0 0 1-1.5 0v-2.292A6.75 6.75 0 0 1 5.25 12v-.75A.75.75 0 0 1 6 10.5Z" />
                        </svg>
                    </template>
                    <template x-if="isRecording">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                            <path fill-rule="evenodd" d="M4.5 7.5A3 3 0 0 1 7.5 4.5h9a3 3 0 0 1 3 3v9a3 3 0 0 1-3 3h-9a3 3 0 0 1-3-3v-9Z" clip-rule="evenodd" />
                        </svg>
                    </template>
                    <template x-if="isTranscribing">
                        <span class="chat-voice-spinner" aria-hidden="true"></span>
                    </template>
                </button>

                <div class="chat-input-wrapper">
                    <template x-if="voiceError">
                        <div class="chat-voice-error" x-text="voiceError"></div>
                    </template>

                    <template x-if="isRecording">
                        <div class="chat-voice-recording">
                            <span class="chat-voice-dot" aria-hidden="true"></span>
                            Recording <span x-text="recordingLabel"></span>
                        </div>
                    </template>

                    {{-- Image previews --}}
                    <template x-if="images.length > 0">
                        <div class="chat-image-preview">
                            <template x-for="(image, index) in images" :key="index">
                                <div class="chat-image-preview-item">
                                    <img :src="image.data" :alt="image.name">
                                    <button type="button" class="chat-image-preview-remove" @click="removeImage(index)">&times;</button>
                                </div>
                            </template>
                        </div>
                    </template>

                    {{-- Mode selector - only visible on first message --}}
                    @if($this->totalMessageCount === 0)
                    <div class="chat-mode-selector">
                        <select
                            wire:model="chatMode"
                            class="chat-mode-select"
                        >
                            <option value="prd">Write PRD</option>
                            <option value="brainstorm">Brainstorm</option>
                            <option value="debug">Debug</option>
                            <option value="code-review">Code Review</option>
                            <option value="refactor">Refactor</option>
                            <option value="normal">Normal Chat</option>
                        </select>
                    </div>
                    @endif

                    <textarea
                        x-ref="promptInput"
                        x-model="prompt"
                        placeholder="{{ $this->totalMessageCount === 0 ? $this->getModePlaceholder() : 'Type a message...' }}"
                        rows="1"
                        class="chat-textarea"
                        @paste="handlePaste($event)"
                        @keydown.enter.prevent="if (!$event.shiftKey) submit()"
                    ></textarea>
                </div>
                <button
                    type="submit"
                    class="chat-submit {{ $this->isRunning ? 'chat-submit-queue' : '' }}"
                    :disabled="!canSend"
                    title="{{ $this->isRunning ? 'Add to queue' : 'Send message' }}"
                >
                    @if($this->isRunning)
                        {{-- Clock icon when queueing --}}
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width: 1.25rem; height: 1.25rem;">
                            <path fill-rule="evenodd" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25ZM12.75 6a.75.75 0 0 0-1.5 0v6c0 .414.336.75.75.75h4.5a.75.75 0 0 0 0-1.5h-3.75V6Z" clip-rule="evenodd" />
                        </svg>
                    @else
                        {{-- Send icon normally --}}
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width: 1.25rem; height: 1.25rem;">
                            <path d="M3.478 2.404a.75.75 0 0 0-.926.941l2.432 7.905H13.5a.75.75 0 0 1 0 1.5H4.984l-2.432 7.905a.75.75 0 0 0 .926.94 60.519 60.519 0 0 0 18.445-8.986.75.75 0 0 0 0-1.218A60.517 60.517 0 0 0 3.478 2.404Z" />
                        </svg>
                    @endif
                </button>
            </form>
        </div>
    </div>

    {{-- Deploy Modal --}}
    @if($showDeployModal)
    <div class="file-preview-overlay">
        <div class="file-preview-modal" style="max-width: 28rem;">
            <div class="file-preview-header">
                <h3 class="file-preview-title" style="font-family: inherit; font-size: 1.125rem; font-weight: 600;">Deploy to Site</h3>
                <button wire:click="closeDeployModal" class="file-preview-close">
                    <svg xmlns="http://www.w3.org/2000/svg" style="width: 1.25rem; height: 1.25rem;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="file-preview-content" style="padding: 1.5rem;">
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">Subdomain</label>
                    <div style="display: flex;">
                        <input
                            type="text"
                            wire:model="deploySubdomain"
                            class="chat-textarea"
                            style="border-radius: 0.5rem 0 0 0.5rem; flex: 1;"
                            placeholder="my-feature"
                        >
                        <span style="border-radius: 0 0.5rem 0.5rem 0; border: 1px solid rgb(209 213 219); border-left: 0; background-color: rgb(243 244 246); padding: 0.5rem 0.75rem; font-size: 0.875rem;">.marin.sh</span>
                    </div>
                </div>

                <div style="font-size: 0.875rem; color: rgb(107 114 128); margin-bottom: 1rem;">
                    <p style="margin: 0 0 0.5rem 0;">Preview:</p>
                    <ul style="margin: 0; padding-left: 1.5rem;">
                        <li>Branch: {{ $deploySubdomain ?: 'subdomain' }}</li>
                        <li>PHP: {{ $deployPhpVersion }}</li>
                        <li>Web directory: {{ $deployWebDirectory }}</li>
                    </ul>
                </div>

                <button
                    type="button"
                    wire:click="$toggle('showAdvancedOptions')"
                    style="font-size: 0.875rem; color: rgb(37 99 235); background: none; border: none; cursor: pointer; padding: 0;"
                >
                    {{ $showAdvancedOptions ? '▼' : '▶' }} Advanced Options
                </button>

                @if($showAdvancedOptions)
                    <div style="border-top: 1px solid rgb(229 231 235); padding-top: 0.75rem; margin-top: 0.75rem;">
                        <div style="margin-bottom: 0.75rem;">
                            <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">PHP Version</label>
                            <select wire:model="deployPhpVersion" class="chat-textarea" style="width: 100%;">
                                <option value="8.4">8.4</option>
                                <option value="8.3">8.3</option>
                                <option value="8.2">8.2</option>
                            </select>
                        </div>
                        <div style="margin-bottom: 0.75rem;">
                            <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">Web Directory</label>
                            <input type="text" wire:model="deployWebDirectory" class="chat-textarea" style="width: 100%;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">Database Name (optional)</label>
                            <input type="text" wire:model="deployDatabaseName" class="chat-textarea" style="width: 100%;">
                        </div>
                    </div>
                @endif
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.5rem; padding: 1rem 1.5rem; border-top: 1px solid rgb(229 231 235);">
                <button
                    wire:click="closeDeployModal"
                    style="border-radius: 0.5rem; border: 1px solid rgb(209 213 219); padding: 0.5rem 1rem; background: white; cursor: pointer;"
                >
                    Cancel
                </button>
                <button
                    wire:click="deployToSite"
                    style="border-radius: 0.5rem; background-color: rgb(22 163 74); padding: 0.5rem 1rem; color: white; border: none; cursor: pointer;"
                >
                    Deploy
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- Image Lightbox Modal --}}
    <div
        x-data="{ open: false, src: '', alt: '' }"
        @open-image-modal.window="open = true; src = $event.detail.src; alt = $event.detail.alt"
        @keydown.escape.window="open = false"
    >
        <template x-if="open">
            <div
                class="chat-image-lightbox-overlay"
                @click.self="open = false"
            >
                <div class="chat-image-lightbox">
                    <button class="chat-image-lightbox-close" @click="open = false">&times;</button>
                    <img :src="src" :alt="alt" class="chat-image-lightbox-img">
                </div>
            </div>
        </template>
    </div>
</div>
