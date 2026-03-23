<div class="flex flex-col h-full overflow-hidden" x-data="{
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
    {{-- Mobile Header (shown only on mobile) --}}
    <div class="chat-mobile-header navbar bg-base-100 border-b border-base-300 px-2 py-1 lg:hidden sticky top-0 z-50">
        <div class="navbar-start w-auto shrink-0">
            <a
                href="{{ route('workbench.tasks.index') }}"
                class="btn btn-ghost btn-sm btn-circle"
                wire:navigate
            >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                </svg>
            </a>
        </div>
        <div class="navbar-center flex-1 min-w-0 px-2 overflow-hidden">
            <div class="flex flex-col items-center min-w-0 w-full">
                <h1 class="text-sm font-semibold text-base-content truncate max-w-full">{{ $task->title ?? ($task->repository?->name ?? 'Chat') }}</h1>
                @if($task->taskSchedule)
                    <span class="badge badge-xs badge-info">
                        Scheduled: {{ $task->taskSchedule->name }}
                    </span>
                @endif
                <p class="text-xs text-base-content/60 truncate max-w-full">
                    {{ $this->locationLabel }}
                    @if($task->isInWorkspace() && $task->isInitializing())
                        <span class="text-warning">
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
        </div>
        <div class="navbar-end w-auto shrink-0 flex items-center gap-1 relative z-[60]">
            {{-- Context indicator --}}
            <div class="flex items-center gap-1" title="{{ $task->is_compacting ? 'Compacting conversation...' : number_format($this->contextUsed) . ' / ' . number_format($this->contextLimit) . ' tokens' }}">
                @if($task->is_compacting)
                    <svg class="animate-spin size-4 text-base-content/60" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                @else
                    <progress class="progress w-10 h-1.5 {{ $this->contextColor }}" value="{{ min($this->contextPercentage, 100) }}" max="100"></progress>
                    <span class="text-xs text-base-content/60">{{ number_format($this->contextPercentage, 0) }}%</span>
                @endif
                @if($task->compaction_count > 0)
                    <span class="badge badge-xs badge-neutral">
                        {{ $task->compaction_count }}
                    </span>
                @endif
            </div>
            {{-- Menu button --}}
            <div class="relative">
                <button @click.stop="mobileMenuOpen = !mobileMenuOpen" type="button" class="btn btn-ghost btn-sm btn-circle">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 12.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 18.75a.75.75 0 110-1.5.75.75 0 010 1.5z" />
                    </svg>
                </button>
                {{-- Mobile Dropdown Menu (fixed position to avoid overflow clipping) --}}
                <div
                    x-show="mobileMenuOpen"
                    @click.away="mobileMenuOpen = false"
                    x-transition:enter="transition ease-out duration-100"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100"
                    x-transition:leave="transition ease-in duration-75"
                    x-transition:leave-start="opacity-100 scale-100"
                    x-transition:leave-end="opacity-0 scale-95"
                    class="fixed right-2 top-14 menu bg-base-200 rounded-box z-[100] w-64 p-2 shadow-lg border border-base-300 max-h-[80vh] overflow-y-auto"
                    x-cloak
                >
                    {{-- Provider selector --}}
                    <div class="flex flex-wrap gap-1 p-2 border-b border-base-300 mb-1">
                        @foreach($this->availableProviders as $provider)
                            <button
                                wire:click="setProvider({{ $provider->id }})"
                                @click="mobileMenuOpen = false"
                                class="btn btn-xs {{ $this->currentProvider?->id === $provider->id ? 'btn-primary' : 'btn-ghost' }}"
                                @disabled($this->isRunning)
                            >
                                {{ $provider->display_name }}
                            </button>
                        @endforeach
                    </div>
                    <li>
                        <button
                            @click="navigator.clipboard.writeText(window.location.href); mobileMenuOpen = false"
                            class="flex items-center gap-2"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" />
                            </svg>
                            <span>Copy Link</span>
                        </button>
                    </li>
                    <li>
                        <button
                            @click="$dispatch('open-sidebar'); mobileMenuOpen = false"
                            class="flex items-center gap-2"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9.776c.112-.017.227-.026.344-.026h15.812c.117 0 .232.009.344.026m-16.5 0a2.25 2.25 0 00-1.883 2.542l.857 6a2.25 2.25 0 002.227 1.932H19.05a2.25 2.25 0 002.227-1.932l.857-6a2.25 2.25 0 00-1.883-2.542m-16.5 0V6A2.25 2.25 0 016 3.75h3.879a1.5 1.5 0 011.06.44l2.122 2.12a1.5 1.5 0 001.06.44H18A2.25 2.25 0 0120.25 9v.776" />
                            </svg>
                            <span>Snippets & Files</span>
                        </button>
                    </li>
                    <li>
                        <a
                            href="{{ \App\Filament\Resources\Tasks\Pages\TaskIde::getUrl(['record' => $task->uuid]) }}"
                            @click="mobileMenuOpen = false"
                            class="flex items-center gap-2"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 6.75L22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3l-4.5 16.5" />
                            </svg>
                            <span>Open IDE</span>
                        </a>
                    </li>
                    @if($task->isInWorkspace())
                        <div class="divider my-0"></div>
                        @if($this->hasEnvConfigs)
                            <li>
                                <button
                                    wire:click="copyEnvConfig"
                                    @click="mobileMenuOpen = false"
                                    class="flex items-center gap-2"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 01-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 011.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 00-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 01-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 00-3.375-3.375h-1.5a1.125 1.125 0 01-1.125-1.125v-1.5a3.375 3.375 0 00-3.375-3.375H9.75" />
                                    </svg>
                                    <span>Copy .env</span>
                                </button>
                            </li>
                        @endif
                        <li>
                            <button
                                wire:click="openDeployModal"
                                @click="mobileMenuOpen = false"
                                class="flex items-center gap-2 text-primary"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0l3 3m-3-3l-3 3M6.75 19.5a4.5 4.5 0 01-1.41-8.775 5.25 5.25 0 0110.233-2.33 3 3 0 013.758 3.848A3.752 3.752 0 0118 19.5H6.75z" />
                                </svg>
                                <span>Deploy to Site</span>
                            </button>
                        </li>
                        <div class="divider my-0"></div>
                        <li>
                            <button
                                wire:click="deleteWorkspace"
                                wire:confirm="Are you sure you want to delete this workspace? This cannot be undone."
                                @click="mobileMenuOpen = false"
                                class="flex items-center gap-2 text-error"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                </svg>
                                <span>Delete Workspace</span>
                            </button>
                        </li>
                    @endif
                    {{-- Ralph Actions --}}
                    @if($task->repository)
                        <div class="divider my-0"></div>
                        <li>
                            <button
                                wire:click="triggerPrdToIssues"
                                @click="mobileMenuOpen = false"
                                wire:loading.attr="disabled"
                                wire:target="triggerPrdToIssues"
                                class="flex items-center gap-2"
                            >
                                <span>🎯</span>
                                <span wire:loading.remove wire:target="triggerPrdToIssues">PRD → Issues</span>
                                <span wire:loading wire:target="triggerPrdToIssues">Processing...</span>
                            </button>
                        </li>
                        @if(!$task->ralph_enabled)
                            <li>
                                <button
                                    wire:click="startRalphLoop"
                                    @click="mobileMenuOpen = false"
                                    wire:loading.attr="disabled"
                                    wire:target="startRalphLoop"
                                    class="flex items-center gap-2"
                                >
                                    <span>🔁</span>
                                    <span wire:loading.remove wire:target="startRalphLoop">Start Ralph</span>
                                    <span wire:loading wire:target="startRalphLoop">Starting...</span>
                                </button>
                            </li>
                        @else
                            <div>
                                @php $ralphMobile = $this->ralphStatus; @endphp
                                @if(($ralphMobile['status'] ?? '') === 'completed')
                                    <li class="disabled">
                                        <span class="flex items-center gap-2 opacity-70">
                                            <span>✅</span>
                                            <span>Ralph Done ({{ $ralphMobile['stories_passed'] ?? 0 }}/{{ $ralphMobile['stories_total'] ?? 0 }})</span>
                                        </span>
                                    </li>
                                @elseif(in_array($ralphMobile['status'] ?? '', ['failed', 'stalled']))
                                    <li>
                                        <button
                                            wire:click="restartRalphLoop"
                                            @click="mobileMenuOpen = false"
                                            wire:loading.attr="disabled"
                                            wire:target="restartRalphLoop"
                                            class="flex items-center gap-2"
                                        >
                                            <span>🔁</span>
                                            <span wire:loading.remove wire:target="restartRalphLoop">Restart Ralph ({{ $ralphMobile['stories_passed'] ?? 0 }}/{{ $ralphMobile['stories_total'] ?? 0 }})</span>
                                            <span wire:loading wire:target="restartRalphLoop">Restarting...</span>
                                        </button>
                                    </li>
                                @else
                                    <li class="disabled">
                                        <span class="flex items-center gap-2 opacity-70">
                                            <svg class="animate-spin size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            <span>Ralph #{{ $ralphMobile['iteration'] ?? '?' }} ({{ $ralphMobile['stories_passed'] ?? 0 }}/{{ $ralphMobile['stories_total'] ?? 0 }})</span>
                                        </span>
                                    </li>
                                @endif
                            </div>
                        @endif
                    @endif
                    {{-- Delete Chat & Workspace (always available) --}}
                    <div class="divider my-0"></div>
                    <li>
                        <button
                            wire:click="deleteTask"
                            wire:confirm="Are you sure you want to delete this chat and all its messages? This cannot be undone."
                            @click="mobileMenuOpen = false"
                            class="flex items-center gap-2 text-error"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                            </svg>
                            <span>{{ $task->isInWorkspace() ? 'Delete Chat & Workspace' : 'Delete Chat' }}</span>
                        </button>
                    </li>
                </div>
            </div>
        </div>
    </div>

    {{-- Desktop Header --}}
    <div class="hidden lg:block bg-base-100 border-b border-base-300">
        <div class="flex items-center gap-3 px-4 py-2">
            {{-- Left: Title + meta --}}
            <div class="flex items-center gap-3 min-w-0 flex-1">
                <h2 class="text-sm font-semibold text-base-content truncate max-w-xs">{{ $task->title ?? ($task->repository?->name ?? 'Chat') }}</h2>

                {{-- Ralph status (only when active) --}}
                @if($task->ralph_enabled && $this->chatMessages->isNotEmpty() && $task->repository)
                    <div class="inline">
                        @php $ralph = $this->ralphStatus; @endphp
                        @if(($ralph['status'] ?? '') === 'completed')
                            <span class="badge badge-xs badge-success" title="Ralph completed all stories">
                                Done {{ $ralph['stories_passed'] ?? 0 }}/{{ $ralph['stories_total'] ?? 0 }}
                            </span>
                        @elseif(in_array($ralph['status'] ?? '', ['failed', 'stalled']))
                            <button
                                wire:click="restartRalphLoop"
                                wire:loading.attr="disabled"
                                wire:target="restartRalphLoop"
                                class="badge badge-xs badge-error cursor-pointer hover:opacity-80"
                                title="{{ ($ralph['status'] ?? '') === 'failed' ? 'Ralph failed' : 'Ralph stalled' }} — click to restart"
                            >
                                <span wire:loading.remove wire:target="restartRalphLoop">Restart {{ $ralph['stories_passed'] ?? 0 }}/{{ $ralph['stories_total'] ?? 0 }}</span>
                                <span wire:loading wire:target="restartRalphLoop">...</span>
                            </button>
                        @else
                            <span
                                class="badge badge-xs badge-warning gap-0.5"
                                title="Ralph loop: Iteration {{ $ralph['iteration'] ?? '?' }} | {{ $ralph['stories_passed'] ?? 0 }}/{{ $ralph['stories_total'] ?? 0 }} stories passed"
                            >
                                <svg class="animate-spin size-2.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Ralph {{ $ralph['stories_passed'] ?? 0 }}/{{ $ralph['stories_total'] ?? 0 }}
                            </span>
                        @endif
                    </div>
                @endif

                {{-- Separator --}}
                <span class="text-base-content/20">|</span>

                {{-- Compact status line --}}
                <div class="flex items-center gap-2 text-xs text-base-content/50">
                    <span>{{ $this->locationLabel }}</span>

                    @if($task->isInWorkspace())
                        <div class="inline-flex items-center gap-1">
                            @if($task->isInitializing())
                                <svg class="animate-spin size-3 text-warning" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span class="text-warning">
                                    @switch($task->init_status)
                                        @case('cloning') Cloning @break
                                        @case('composer_install') Composer @break
                                        @case('npm_install') npm @break
                                        @case('npm_build') Building @break
                                        @default Init @break
                                    @endswitch
                                </span>
                            @else
                                @php
                                    $initSteps = collect([
                                        $task->ran_composer_install ? 'Composer installed' : null,
                                        $task->ran_npm_install ? 'npm installed' : null,
                                        $task->ran_npm_build ? 'npm build done' : null,
                                    ])->filter()->values();
                                @endphp
                                @if($initSteps->isNotEmpty())
                                    <div class="tooltip tooltip-bottom" data-tip="{{ $initSteps->join(', ') }}">
                                        <span class="text-success cursor-default">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-3.5">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                                            </svg>
                                        </span>
                                    </div>
                                @endif
                            @endif
                        </div>
                    @endif

                    {{-- Context bar --}}
                    @if($task->is_compacting)
                        <span class="inline-flex items-center gap-1 text-warning">
                            <svg class="animate-spin size-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1" title="{{ number_format($this->contextUsed) }} / {{ number_format($this->contextLimit) }} tokens">
                            <progress class="progress w-12 h-1 {{ $this->contextColor }}" value="{{ min($this->contextPercentage, 100) }}" max="100"></progress>
                            <span class="tabular-nums">{{ number_format($this->contextPercentage, 0) }}%</span>
                        </span>
                    @endif
                    @if($task->compaction_count > 0)
                        <span title="{{ $task->compaction_count }} {{ Str::plural('compaction', $task->compaction_count) }}">x{{ $task->compaction_count }}</span>
                    @endif
                </div>
            </div>

            {{-- Right: Actions + Providers --}}
            <div class="flex items-center gap-1.5">
                {{-- Quick actions --}}
                <a
                    href="{{ \App\Filament\Resources\Tasks\Pages\TaskIde::getUrl(['record' => $task->uuid]) }}"
                    class="btn btn-xs btn-ghost"
                    title="Open in IDE"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-3.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 6.75L22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3l-4.5 16.5" />
                    </svg>
                </a>

                @if($task->isInWorkspace())
                    <button wire:click="openDeployModal" class="btn btn-xs btn-ghost text-success" title="Deploy">
                        <x-heroicon-o-cloud-arrow-up class="size-3.5" />
                    </button>
                @endif

                {{-- More menu --}}
                <div x-data="{ open: false }" class="dropdown dropdown-end">
                    <button @click="open = !open" class="btn btn-xs btn-ghost" title="More actions">
                        <x-heroicon-o-ellipsis-vertical class="size-3.5" />
                    </button>
                    <ul x-show="open" @click.away="open = false" x-transition class="dropdown-content menu bg-base-200 rounded-box z-50 w-48 p-1.5 shadow-lg border border-base-300 mt-1">
                        @if($this->chatMessages->isNotEmpty() && $task->repository)
                            <li>
                                <button wire:click="triggerPrdToIssues" wire:loading.attr="disabled" wire:target="triggerPrdToIssues" @click="open = false">
                                    <span wire:loading.remove wire:target="triggerPrdToIssues">PRD to Issues</span>
                                    <span wire:loading wire:target="triggerPrdToIssues">Processing...</span>
                                </button>
                            </li>
                            @if(!$task->ralph_enabled)
                                <li>
                                    <button wire:click="startRalphLoop" wire:loading.attr="disabled" wire:target="startRalphLoop" @click="open = false">
                                        <span wire:loading.remove wire:target="startRalphLoop">Start Ralph</span>
                                        <span wire:loading wire:target="startRalphLoop">Starting...</span>
                                    </button>
                                </li>
                            @endif
                        @endif
                        @if($task->isInWorkspace())
                            @if($this->hasEnvConfigs)
                                <li>
                                    <button wire:click="copyEnvConfig" @click="open = false">Copy .env</button>
                                </li>
                                @if($this->envConfigs->count() > 1)
                                    @foreach($this->envConfigs as $config)
                                        <li>
                                            <button wire:click="copyEnvConfig({{ $config->id }})" @click="open = false" class="pl-6 text-xs">
                                                @if($config->is_default)<x-heroicon-o-star class="size-3 text-warning" />@endif
                                                {{ $config->name }}
                                            </button>
                                        </li>
                                    @endforeach
                                @endif
                            @endif
                            <div class="divider my-0.5"></div>
                            <li>
                                <button
                                    wire:click="deleteWorkspace"
                                    wire:confirm="Are you sure you want to delete this workspace? This cannot be undone."
                                    @click="open = false"
                                    class="text-error"
                                >
                                    <x-heroicon-o-trash class="size-3.5" /> Delete Workspace
                                </button>
                            </li>
                        @endif
                        <li>
                            <button
                                wire:click="deleteTask"
                                wire:confirm="Are you sure you want to delete this chat and all its messages? This cannot be undone."
                                @click="open = false"
                                class="text-error"
                            >
                                <x-heroicon-o-trash class="size-3.5" /> {{ $task->isInWorkspace() ? 'Delete Chat & Workspace' : 'Delete Chat' }}
                            </button>
                        </li>
                    </ul>
                </div>

                {{-- Separator --}}
                <span class="text-base-content/20">|</span>

                {{-- Provider selector --}}
                <div class="flex items-center gap-0.5">
                    @foreach($this->availableProviders as $provider)
                        <button
                            wire:click="setProvider({{ $provider->id }})"
                            class="btn btn-xs {{ $this->currentProvider?->id === $provider->id ? 'btn-primary' : 'btn-ghost' }}"
                            @disabled($this->isRunning)
                        >
                            {{ $provider->display_name }}
                        </button>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- Chat Area --}}
    <div class="flex flex-col flex-1 min-h-0"
        x-data="{
            polling: @entangle('waitingForResponse').live,
            isNearBottom: true,
            scrollThreshold: 150,
            pendingScroll: null,
            lastStableScrollTop: 0,
            loadingOlderMessages: false,
            prependScrollState: null,
            historyObserver: null,
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
                    const chatClass = isUser ? 'chat chat-end chat-message chat-message-user' : 'chat chat-start chat-message chat-message-assistant';
                    const bubbleClass = isUser ? 'chat-bubble chat-bubble-primary' : 'chat-bubble';
                    return `
                        <div class='${chatClass}'>
                            <div class='${bubbleClass}'>
                                <div class='chat-bubble-content prose prose-sm max-w-none'>${escapeHtml(m.text || '')}</div>
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
                if (!el) return;
                this.isNearBottom = (el.scrollHeight - el.scrollTop - el.clientHeight) < this.scrollThreshold;
                this.lastStableScrollTop = el.scrollTop;
            },
            scrollToBottom() {
                const el = this.$refs.messages;
                if (!el) return;
                el.scrollTop = el.scrollHeight;
                this.lastStableScrollTop = el.scrollTop;
            },
            prepareForHistoryPrepend() {
                const el = this.$refs.messages;
                if (!el) return;

                this.prependScrollState = {
                    scrollHeight: el.scrollHeight,
                    scrollTop: el.scrollTop,
                };
            },
            restoreAfterHistoryPrepend() {
                const el = this.$refs.messages;
                if (!el || !this.prependScrollState) {
                    this.loadingOlderMessages = false;
                    return;
                }

                const delta = el.scrollHeight - this.prependScrollState.scrollHeight;
                el.scrollTop = this.prependScrollState.scrollTop + delta;
                this.lastStableScrollTop = el.scrollTop;
                this.prependScrollState = null;
                this.loadingOlderMessages = false;
            },
            observeHistoryTop() {
                if (this.historyObserver) {
                    this.historyObserver.disconnect();
                    this.historyObserver = null;
                }

                const root = this.$refs.messages;
                const sentinel = this.$refs.historyTopSentinel;

                if (!root || !sentinel) return;

                this.historyObserver = new IntersectionObserver((entries) => {
                    const entry = entries[0];

                    if (!entry?.isIntersecting || this.loadingOlderMessages) {
                        return;
                    }

                    this.loadingOlderMessages = true;
                    this.prepareForHistoryPrepend();
                    $wire.loadMoreMessages();
                }, {
                    root,
                    threshold: 0.1,
                });

                this.historyObserver.observe(sentinel);
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
                this.lastStableScrollTop = this.$refs.messages.scrollTop;

                this.$refs.messages.addEventListener('scroll', () => this.checkIfNearBottom());
                this.$el.addEventListener('chat-scroll-bottom', () => {
                    this.isNearBottom = true;
                    this.$nextTick(() => this.scrollToBottom());
                });
                const observer = new MutationObserver(() => {
                    const previousScrollTop = this.$refs.messages.scrollTop;
                    this.$nextTick(() => {
                        if (!this.$refs.messages) return;
                        if (this.isNearBottom) {
                            this.scrollToBottom();
                            this.observeHistoryTop();
                            return;
                        }
                        this.$refs.messages.scrollTop = previousScrollTop;
                        this.lastStableScrollTop = this.$refs.messages.scrollTop;
                        this.observeHistoryTop();
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
                    observer.disconnect();
                    if (this.historyObserver) {
                        this.historyObserver.disconnect();
                    }
                });

                const setupEcho = () => {
                    if (window.Echo) {
                        window.Echo.private('tasks.' + this.taskUuid)
                            .listen('.TaskChatUpdated', (e) => {
                                $wire.handleBroadcastUpdate(e);
                            })
                            .listen('.TaskStatusUpdated', (e) => {
                                $wire.handleBroadcastUpdate({ type: 'status_change' });
                            });
                    } else {
                        setTimeout(setupEcho, 500);
                    }
                };
                setupEcho();

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
                                this.observeHistoryTop();
                                return;
                            }
                            this.$refs.messages.scrollTop = scrollTop;
                            this.observeHistoryTop();
                        });
                    } else {
                        // Default chat behavior: stay pinned to bottom when opening.
                        this.$nextTick(() => {
                            this.scrollToBottom();
                            this.observeHistoryTop();
                        });
                    }

                    // Refresh cache from the real DOM.
                    this.$nextTick(() => this.saveMessagesToCache());
                });

                document.addEventListener('chat-history-prepended', () => {
                    this.$nextTick(() => {
                        this.restoreAfterHistoryPrepend();
                        this.observeHistoryTop();
                        this.saveMessagesToCache();
                    });
                });
            }
        }"
    >
        {{-- Messages --}}
        <div
            class="flex-1 overflow-y-auto p-4 space-y-1"
            x-ref="messages"
            wire:init="loadMessages"
            @if($this->shouldPoll) wire:poll.30s.visible="checkPolling" @endif
        >
            <div class="chat-cached-history" wire:ignore x-ref="cachedHistory"></div>

            @if($this->hasHiddenMessages)
                <div
                    wire:key="chat-history-sentinel-{{ $this->visibleMessageCount }}"
                    x-ref="historyTopSentinel"
                    class="flex justify-center py-2 text-xs text-base-content/45"
                >
                    <span x-show="loadingOlderMessages">Loading older messages...</span>
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
                    $isRalphMessage = !$message->isFromUser() && $message->content && (str_starts_with($message->content, '## Ralph') || str_starts_with($message->content, '**Ralph'));
                @endphp

                @if($showDateSeparator)
                    <div class="divider text-xs text-base-content/50 my-4">
                            @if($message->created_at->isToday())
                                Today
                            @elseif($message->created_at->isYesterday())
                                Yesterday
                            @elseif($message->created_at->year === now()->year)
                                {{ $message->created_at->format('M j') }}
                            @else
                                {{ $message->created_at->format('M j, Y') }}
                            @endif
                    </div>
                @endif
                @if($shouldRenderFirstBubble)
                <div wire:key="message-{{ $message->id }}" class="chat {{ $message->isFromUser() ? 'chat-end' : 'chat-start' }} chat-message {{ $message->isFromUser() ? 'chat-message-user' : 'chat-message-assistant' }} {{ $isRalphMessage ? 'chat-message-ralph' : '' }}">
                    <div class="chat-bubble {{ $message->isFromUser() ? 'chat-bubble-primary' : '' }} {{ $isRalphMessage ? 'chat-bubble-accent' : '' }} max-w-[85%] lg:max-w-[70%]">
                        @if($message->isFromUser())
                            @if($message->images && count($message->images) > 0)
                                <div class="flex flex-wrap gap-2 mb-2">
                                    @foreach($message->images as $imageIndex => $image)
                                        <img
                                            src="{{ $image['data'] }}"
                                            alt="{{ $image['name'] ?? 'Image' }}"
                                            class="w-20 h-20 rounded-lg object-cover cursor-pointer hover:opacity-80 transition-opacity"
                                            @click="$dispatch('open-image-modal', { src: '{{ $image['data'] }}', alt: '{{ $image['name'] ?? 'Image' }}' })"
                                        >
                                    @endforeach
                                </div>
                            @endif
                            @if($message->content)
                                <p style="white-space: pre-wrap; margin: 0;">{!! $message->linkifyContent() !!}</p>
                            @endif
                            <div class="chat-footer opacity-50 text-xs mt-1">
                                {{ $message->created_at->timezone(config('app.timezone'))->format('H:i') }}
                            </div>
                        @else
                            @if($firstBlockIsText)
                                {{-- Render first text block (using cached markdown) --}}
                                <div class="chat-bubble-content prose prose-sm max-w-none dark:prose-invert">
                                    {!! $message->getFirstTextBlockHtml() !!}
                                </div>

                                <div class="flex items-center gap-2 mt-1">
                                    @php
                                        $firstBlockTimestamp = isset($message->content_blocks[0]['timestamp'])
                                            ? \Carbon\Carbon::parse($message->content_blocks[0]['timestamp'])->timezone(config('app.timezone'))->format('H:i')
                                            : $message->created_at->timezone(config('app.timezone'))->format('H:i');
                                    @endphp
                                    <span class="text-xs opacity-50">{{ $firstBlockTimestamp }}</span>
                                    {{-- Only show tokens on the last bubble --}}
                                    @if(count($message->content_blocks) <= 1 && ($message->tokens_in || $message->tokens_out))
                                        <span class="badge badge-ghost badge-xs">
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
                                        class="btn btn-ghost btn-xs mt-2 gap-1"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 9V4.5M9 9H4.5M9 9L3.75 3.75M9 15v4.5M9 15H4.5M9 15l-5.25 5.25M15 9h4.5M15 9V4.5M15 9l5.25-5.25M15 15h4.5M15 15v4.5m0-4.5l5.25 5.25" />
                                        </svg>
                                        Click to compact context
                                    </button>
                                @endif
                            @elseif($hasNoContentBlocks)
                                {{-- Fallback for old messages without content_blocks --}}
                                <div class="chat-bubble-content prose prose-sm max-w-none dark:prose-invert">
                                    {!! Str::markdown($message->content ?? '') !!}
                                </div>

                                @if($message->tool_calls && count($message->tool_calls) > 0)
                                    <div x-data="{ showTools: false }" class="mt-2">
                                        <button
                                            type="button"
                                            @click="showTools = !showTools"
                                            class="btn btn-ghost btn-xs gap-1"
                                        >
                                            <span x-text="showTools ? '▼' : '▶'" class="text-xs"></span>
                                            <span>{{ count($message->tool_calls) }} tool {{ Str::plural('call', count($message->tool_calls)) }}</span>
                                        </button>
                                        <div x-show="showTools" x-collapse class="mt-1 space-y-1">
                                            @foreach($message->tool_calls as $tool)
                                                <div class="collapse collapse-arrow bg-base-200/50 rounded-lg">
                                                    <input type="checkbox">
                                                    <div class="collapse-title text-xs font-medium py-1 min-h-0">{{ $tool['name'] ?? 'Tool' }}</div>
                                                    <div class="collapse-content">
                                                        <pre class="text-xs overflow-x-auto">{{ json_encode($tool['input'] ?? [], JSON_PRETTY_PRINT) }}</pre>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                <div class="flex items-center gap-2 mt-1">
                                    <span class="text-xs opacity-50">{{ $message->created_at->timezone(config('app.timezone'))->format('H:i') }}</span>
                                    @if($message->tokens_in || $message->tokens_out)
                                        <span class="badge badge-ghost badge-xs">
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
                                        class="btn btn-ghost btn-xs mt-2 gap-1"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
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
                        <div wire:key="message-{{ $message->id }}-truncated" class="chat chat-start chat-message chat-message-assistant">
                            <div class="chat-bubble chat-bubble-warning max-w-[85%] lg:max-w-[70%]">
                                <span class="text-xs flex items-center gap-1 flex-wrap">
                                    ⚠️ Showing last {{ count($groupedBlocks) }} of {{ $totalBlockCount }} blocks ({{ $totalBlockCount - count($groupedBlocks) }} hidden for performance)
                                    <button
                                        wire:click="toggleExpandMessage({{ $message->id }})"
                                        class="link link-hover font-semibold"
                                    >
                                        Show all blocks
                                    </button>
                                </span>
                            </div>
                        </div>
                    @elseif($isExpanded && $totalBlockCount > \App\Models\Message::MAX_RENDERED_BLOCKS)
                        {{-- Show collapse option when expanded --}}
                        <div wire:key="message-{{ $message->id }}-expanded" class="chat chat-start chat-message chat-message-assistant">
                            <div class="chat-bubble chat-bubble-success max-w-[85%] lg:max-w-[70%]">
                                <span class="text-xs flex items-center gap-1 flex-wrap">
                                    Showing all {{ $totalBlockCount }} blocks
                                    <button
                                        wire:click="toggleExpandMessage({{ $message->id }})"
                                        class="link link-hover font-semibold"
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
                                 class="chat chat-start chat-message chat-message-assistant">
                                <div class="chat-bubble max-w-[85%] lg:max-w-[70%]">
                                    <div class="chat-bubble-content prose prose-sm max-w-none dark:prose-invert">
                                        {!! $message->renderMarkdown($block['text']) !!}
                                    </div>
                                    <div class="flex items-center gap-2 mt-1">
                                        <span class="text-xs opacity-50">{{ $blockTimestamp }}</span>
                                        @if($isLastBlock && ($message->tokens_in || $message->tokens_out))
                                            <span class="badge badge-ghost badge-xs">
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
                                 class="chat chat-start chat-message chat-message-assistant">
                                <div class="chat-bubble bg-base-200 text-base-content max-w-[85%] lg:max-w-[70%]">
                                    <span class="text-xs text-base-content/70 italic">{{ $block['text'] }}</span>
                                    <span class="badge badge-sm badge-neutral ml-1">{{ $block['count'] }}</span>
                                </div>
                            </div>
                        @elseif(($block['type'] ?? '') === 'tool_group')
                            <div wire:key="message-{{ $message->id }}-grouped-{{ $blockIndex }}"
                                 class="chat chat-start chat-message chat-message-assistant">
                                <div class="chat-bubble bg-base-200 text-base-content max-w-[85%] lg:max-w-[70%]">
                                    @if(count($block['tools']) === 1)
                                        @php
                                            $toolCommand = $block['tools'][0]['tool']['input']['command'] ?? null;
                                        @endphp
                                        <div class="flex items-center gap-1.5 text-xs">
                                            <span class="opacity-60">⚙</span>
                                            <span
                                                class="font-mono text-xs opacity-80"
                                                @if($toolCommand)
                                                    title="{{ $toolCommand }}"
                                                @endif
                                            >
                                                {{ $block['tools'][0]['tool']['name'] ?? 'Tool' }}
                                            </span>
                                        </div>
                                    @else
                                        <div x-data="{ showTools: false }">
                                            <button
                                                type="button"
                                                @click="showTools = !showTools"
                                                class="btn btn-ghost btn-xs gap-1 -ml-1"
                                            >
                                                <span x-text="showTools ? '▼' : '▶'" class="text-xs"></span>
                                                <span>{{ count($block['tools']) }} tool {{ Str::plural('call', count($block['tools'])) }}</span>
                                            </button>
                                            <div x-show="showTools" x-collapse class="mt-1 space-y-1">
                                            @foreach($block['tools'] as $tool)
                                                @php
                                                    $toolCommand = $tool['tool']['input']['command'] ?? null;
                                                @endphp
                                                <div class="flex items-center gap-1.5 text-xs mb-1">
                                                    <span class="opacity-60">⚙</span>
                                                    <span
                                                        class="font-mono text-xs opacity-80"
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
                                    <div class="flex items-center gap-2 mt-1">
                                        <span class="text-xs opacity-50">{{ $blockTimestamp }}</span>
                                        @if($isLastBlock && ($message->tokens_in || $message->tokens_out))
                                            <span class="badge badge-ghost badge-xs">
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
                                 class="chat chat-start chat-message chat-message-assistant"
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
                                <div class="chat-bubble chat-bubble-info max-w-[85%] lg:max-w-[70%]">
                                    <div class="flex items-center gap-2 font-semibold text-sm mb-2">
                                        <span>❓</span>
                                        <span>{{ $this->providerLabel }} needs your input</span>
                                    </div>

                                    <div class="space-y-4">
                                        @foreach($questions as $qIndex => $question)
                                            <div class="space-y-2">
                                                @if(!empty($question['header']))
                                                    <span class="badge badge-outline badge-sm">{{ $question['header'] }}</span>
                                                @endif
                                                <p class="text-sm font-medium">{{ $question['question'] ?? 'Please select an option:' }}</p>

                                                <div class="space-y-1.5">
                                                    @foreach($question['options'] ?? [] as $oIndex => $option)
                                                        @php $optionLabel = $option['label'] ?? $option['description'] ?? "Option " . ($oIndex + 1); @endphp
                                                        @if($question['multiSelect'] ?? false)
                                                            {{-- Multi-select: checkboxes --}}
                                                            <label class="flex items-center gap-2 p-2 rounded-lg border border-base-300 cursor-pointer hover:bg-base-200 transition-colors"
                                                                   :class="{ 'border-primary bg-primary/10': isMultiSelected({{ $qIndex }}, '{{ addslashes($optionLabel) }}'), 'opacity-50 pointer-events-none': submitted }">
                                                                <input type="checkbox"
                                                                       :disabled="submitted"
                                                                       @change="toggleMultiSelect({{ $qIndex }}, '{{ addslashes($optionLabel) }}')"
                                                                       :checked="isMultiSelected({{ $qIndex }}, '{{ addslashes($optionLabel) }}')"
                                                                       class="checkbox checkbox-sm checkbox-primary">
                                                                <span class="text-sm">{{ $optionLabel }}</span>
                                                                @if(!empty($option['description']) && isset($option['label']))
                                                                    <span class="text-xs opacity-60">{{ $option['description'] }}</span>
                                                                @endif
                                                            </label>
                                                        @else
                                                            {{-- Single select: radio buttons --}}
                                                            <label class="flex items-center gap-2 p-2 rounded-lg border border-base-300 cursor-pointer hover:bg-base-200 transition-colors"
                                                                   :class="{ 'border-primary bg-primary/10': responses[{{ $qIndex }}] === '{{ addslashes($optionLabel) }}', 'opacity-50 pointer-events-none': submitted }">
                                                                <input type="radio"
                                                                       name="question-{{ $message->id }}-{{ $qIndex }}"
                                                                       value="{{ $optionLabel }}"
                                                                       :disabled="submitted"
                                                                       x-model="responses[{{ $qIndex }}]"
                                                                       class="radio radio-sm radio-primary">
                                                                <span class="text-sm">{{ $optionLabel }}</span>
                                                                @if(!empty($option['description']) && isset($option['label']))
                                                                    <span class="text-xs opacity-60">{{ $option['description'] }}</span>
                                                                @endif
                                                            </label>
                                                        @endif
                                                    @endforeach

                                                    {{-- "Other" option with text input --}}
                                                    <label class="flex items-center gap-2 p-2 rounded-lg border border-base-300 cursor-pointer hover:bg-base-200 transition-colors"
                                                           :class="{ 'border-primary bg-primary/10': responses[{{ $qIndex }}] === '__other__', 'opacity-50 pointer-events-none': submitted }">
                                                        <input type="radio"
                                                               name="question-{{ $message->id }}-{{ $qIndex }}"
                                                               value="__other__"
                                                               :disabled="submitted"
                                                               x-model="responses[{{ $qIndex }}]"
                                                               class="radio radio-sm radio-primary">
                                                        <span class="text-sm">Other</span>
                                                    </label>
                                                    <div x-show="responses[{{ $qIndex }}] === '__other__'" x-collapse>
                                                        <input type="text"
                                                               x-model="otherText[{{ $qIndex }}]"
                                                               :disabled="submitted"
                                                               placeholder="Enter your response..."
                                                               class="input input-bordered input-sm w-full mt-1">
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>

                                    <div class="mt-3">
                                        <button type="button"
                                                @click="submit()"
                                                :disabled="submitting || submitted || Object.keys(responses).length !== {{ count($questions) }}"
                                                class="btn btn-sm btn-primary"
                                                :class="{ 'btn-success': submitted }">
                                            <span x-show="!submitting && !submitted">Submit Response</span>
                                            <span x-show="submitting">Sending...</span>
                                            <span x-show="submitted">✓ Response Sent</span>
                                        </button>
                                    </div>

                                    <div class="flex items-center gap-2 mt-1">
                                        <span class="text-xs opacity-50">{{ $blockTimestamp }}</span>
                                    </div>
                                </div>
                            </div>
                        @endif
                    @endforeach
                    @endif
                @endif
            @empty
                @if(! $this->messagesLoaded && $this->lastMessageCount > 0)
                    <div class="flex items-center justify-center h-full">
                        <p class="text-base-content/50">Loading messages…</p>
                    </div>
                @else
                    <div class="flex items-center justify-center h-full">
                        <p class="text-base-content/50">Start a conversation with {{ $this->providerLabel }}</p>
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
            <div class="chat chat-start">
                <div class="chat-bubble bg-base-200 text-base-content max-w-[85%] lg:max-w-[70%]">
                    <div class="flex items-center gap-2">
                        <span class="loading loading-dots loading-sm"></span>
                        <span class="text-sm opacity-70">
                            @if($this->hasActiveSubagents && !$this->isRunning)
                                Subagents working...
                            @else
                                {{ $this->providerLabel }} is thinking...
                            @endif
                        </span>
                    </div>
                    @if($showActivity)
                        <div x-data="{ showActivity: false }" class="mt-2">
                            <button
                                type="button"
                                @click="showActivity = !showActivity"
                                class="btn btn-ghost btn-xs gap-1 -ml-1"
                            >
                                <span x-text="showActivity ? '▼' : '▶'"></span>
                                <span>Activity ({{ count($activityEvents) }})</span>
                            </button>
                            <div x-show="showActivity" x-collapse class="mt-1 space-y-0.5">
                                @foreach($activityEvents as $event)
                                    <div class="text-xs opacity-60 font-mono">{{ $event }}</div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    <button
                        type="button"
                        wire:click="stopRunning"
                        wire:loading.attr="disabled"
                        class="btn btn-error btn-xs mt-2 gap-1"
                        title="Stop {{ $this->providerLabel }}"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4">
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
            <div class="border-t border-base-300 bg-base-200/50 px-4 py-2 space-y-1.5">
                @foreach($this->queuedMessages as $queuedMessage)
                    <div wire:key="queued-{{ $queuedMessage->id }}" class="flex items-center justify-between gap-2 bg-base-100 rounded-lg px-3 py-1.5 text-sm">
                        <div class="flex items-center gap-2 min-w-0 flex-1">
                            @if($queuedMessage->images && count($queuedMessage->images) > 0)
                                <span class="flex items-center gap-1 text-base-content/60 shrink-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                                    </svg>
                                    {{ count($queuedMessage->images) }}
                                </span>
                            @endif
                            @if($queuedMessage->content)
                                <span class="truncate text-base-content/80">{{ Str::limit($queuedMessage->content, 100) }}</span>
                            @endif
                        </div>
                        <button
                            type="button"
                            wire:click="deleteQueuedMessage({{ $queuedMessage->id }})"
                            class="btn btn-ghost btn-xs btn-circle"
                            title="Remove from queue"
                        >&times;</button>
                    </div>
                @endforeach
                <div class="flex items-center gap-1.5 text-xs text-base-content/50">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                    {{ $this->queuedMessages->count() }} {{ Str::plural('message', $this->queuedMessages->count()) }} queued - will send when {{ $this->providerLabel }} finishes
                </div>
            </div>
        @endif

{{-- Input --}}
        <div class="shrink-0 border-t border-base-300 bg-base-100 p-3"
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
                    this.prompt = '';
                    this.clearDraft();
                    // Force scroll to bottom before and after Livewire re-renders
                    this.$dispatch('chat-scroll-bottom');
                    $wire.sendMessage().then(() => {
                        this.$dispatch('chat-scroll-bottom');
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
                class="hidden"
            >

            {{-- Hidden file input fallback for audio selection --}}
            <input
                type="file"
                x-ref="audioInput"
                @change="handleAudioSelect($event)"
                accept="audio/*"
                class="hidden"
            >

            <form @submit.prevent="submit()" class="flex items-end gap-2">
                {{-- Attachment button (primarily for mobile) --}}
                <button
                    type="button"
                    @click="openFilePicker()"
                    class="btn btn-ghost btn-sm btn-circle shrink-0"
                    title="Attach image"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z" />
                    </svg>
                </button>

                {{-- Voice message (tap to record / tap to stop) --}}
                <button
                    type="button"
                    @click="toggleRecording()"
                    class="btn btn-ghost btn-sm btn-circle shrink-0"
                    :class="{ 'btn-error text-error-content': isRecording, 'btn-disabled opacity-50': isTranscribing }"
                    :disabled="isTranscribing"
                    :title="isRecording ? 'Stop recording' : 'Record voice message'"
                >
                    <template x-if="!isRecording && !isTranscribing">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5">
                            <path d="M8.25 12a3.75 3.75 0 1 0 7.5 0V6a3.75 3.75 0 1 0-7.5 0v6Z" />
                            <path d="M6 10.5a.75.75 0 0 1 .75.75V12a5.25 5.25 0 0 0 10.5 0v-.75a.75.75 0 0 1 1.5 0V12a6.75 6.75 0 0 1-6 6.708V21a.75.75 0 0 1-1.5 0v-2.292A6.75 6.75 0 0 1 5.25 12v-.75A.75.75 0 0 1 6 10.5Z" />
                        </svg>
                    </template>
                    <template x-if="isRecording">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5">
                            <path fill-rule="evenodd" d="M4.5 7.5A3 3 0 0 1 7.5 4.5h9a3 3 0 0 1 3 3v9a3 3 0 0 1-3 3h-9a3 3 0 0 1-3-3v-9Z" clip-rule="evenodd" />
                        </svg>
                    </template>
                    <template x-if="isTranscribing">
                        <span class="loading loading-spinner loading-xs" aria-hidden="true"></span>
                    </template>
                </button>

                <div class="flex-1 min-w-0">
                    <template x-if="voiceError">
                        <div class="text-error text-xs mb-1 px-1" x-text="voiceError"></div>
                    </template>

                    <template x-if="isRecording">
                        <div class="flex items-center gap-2 text-sm text-error px-1 mb-1">
                            <span class="w-2 h-2 rounded-full bg-error animate-pulse" aria-hidden="true"></span>
                            Recording <span x-text="recordingLabel"></span>
                        </div>
                    </template>

                    {{-- Image previews --}}
                    <template x-if="images.length > 0">
                        <div class="flex flex-wrap gap-2 mb-2">
                            <template x-for="(image, index) in images" :key="index">
                                <div class="relative">
                                    <img :src="image.data" :alt="image.name" class="w-16 h-16 rounded-lg object-cover">
                                    <button type="button" class="btn btn-circle btn-xs btn-error absolute -top-1 -right-1" @click="removeImage(index)">&times;</button>
                                </div>
                            </template>
                        </div>
                    </template>

                    {{-- Mode selector - only visible on first message --}}
                    @if($this->totalMessageCount === 0)
                    <div class="mb-2">
                        <select
                            wire:model="chatMode"
                            class="select select-bordered select-xs w-full max-w-xs"
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
                        class="textarea textarea-bordered w-full min-h-[2.5rem] max-h-40 resize-none leading-snug"
                        @paste="handlePaste($event)"
                        @keydown.enter.prevent="if (!$event.shiftKey) submit()"
                    ></textarea>
                </div>
                <button
                    type="submit"
                    class="btn btn-primary btn-circle shrink-0 {{ $this->isRunning ? 'btn-warning' : '' }}"
                    :disabled="!canSend"
                    title="{{ $this->isRunning ? 'Add to queue' : 'Send message' }}"
                >
                    @if($this->isRunning)
                        {{-- Clock icon when queueing --}}
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5">
                            <path fill-rule="evenodd" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25ZM12.75 6a.75.75 0 0 0-1.5 0v6c0 .414.336.75.75.75h4.5a.75.75 0 0 0 0-1.5h-3.75V6Z" clip-rule="evenodd" />
                        </svg>
                    @else
                        {{-- Send icon normally --}}
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5">
                            <path d="M3.478 2.404a.75.75 0 0 0-.926.941l2.432 7.905H13.5a.75.75 0 0 1 0 1.5H4.984l-2.432 7.905a.75.75 0 0 0 .926.94 60.519 60.519 0 0 0 18.445-8.986.75.75 0 0 0 0-1.218A60.517 60.517 0 0 0 3.478 2.404Z" />
                        </svg>
                    @endif
                </button>
            </form>
        </div>
    </div>

    {{-- Deploy Modal --}}
    @if($showDeployModal)
    <div class="modal modal-open">
        <div class="modal-box max-w-md">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold">Deploy to Site</h3>
                <button wire:click="closeDeployModal" class="btn btn-sm btn-circle btn-ghost">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="space-y-4">
                <div>
                    <label class="label text-sm font-medium">Subdomain</label>
                    <div class="flex">
                        <input
                            type="text"
                            wire:model="deploySubdomain"
                            class="input input-bordered flex-1 rounded-r-none"
                            placeholder="my-feature"
                        >
                        <span class="inline-flex items-center rounded-r-lg border border-l-0 border-base-300 bg-base-200 px-3 text-sm text-base-content/70">.marin.sh</span>
                    </div>
                </div>

                <div class="rounded-lg bg-base-200 p-3 text-sm">
                    <p class="mb-2">Preview:</p>
                    <ul class="list-disc pl-6">
                        <li>Branch: {{ $deploySubdomain ?: 'subdomain' }}</li>
                        <li>PHP: {{ $deployPhpVersion }}</li>
                        <li>Web directory: {{ $deployWebDirectory }}</li>
                    </ul>
                </div>

                <button
                    type="button"
                    wire:click="$toggle('showAdvancedOptions')"
                    class="btn btn-ghost btn-sm px-0"
                >
                    {{ $showAdvancedOptions ? '▼' : '▶' }} Advanced Options
                </button>

                @if($showAdvancedOptions)
                    <div class="space-y-3">
                        <div>
                            <label class="label text-sm font-medium">PHP Version</label>
                            <select wire:model="deployPhpVersion" class="select select-bordered w-full">
                                <option value="8.4">8.4</option>
                                <option value="8.3">8.3</option>
                                <option value="8.2">8.2</option>
                            </select>
                        </div>
                        <div>
                            <label class="label text-sm font-medium">Web Directory</label>
                            <input type="text" wire:model="deployWebDirectory" class="input input-bordered w-full">
                        </div>
                        <div>
                            <label class="label text-sm font-medium">Database Name (optional)</label>
                            <input type="text" wire:model="deployDatabaseName" class="input input-bordered w-full">
                        </div>
                    </div>
                @endif
            </div>

            <div class="modal-action">
                <button
                    wire:click="closeDeployModal"
                    class="btn btn-ghost"
                >
                    Cancel
                </button>
                <button
                    wire:click="deployToSite"
                    class="btn btn-primary"
                >
                    Deploy
                </button>
            </div>
        </div>
        <div class="modal-backdrop" wire:click="closeDeployModal"></div>
    </div>
    @endif

    {{-- Image Lightbox Modal --}}
    <div
        x-data="{ open: false, src: '', alt: '' }"
        @open-image-modal.window="open = true; src = $event.detail.src; alt = $event.detail.alt"
        @keydown.escape.window="open = false"
    >
        <template x-if="open">
            <div class="modal modal-open">
                <div class="modal-box max-w-4xl p-2">
                    <button class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2 z-10" @click="open = false">&times;</button>
                    <img :src="src" :alt="alt" class="w-full rounded">
                </div>
                <div class="modal-backdrop" @click="open = false"></div>
            </div>
        </template>
    </div>
</div>
