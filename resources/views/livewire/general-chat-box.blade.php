<div class="chat-container" x-data="{
    mobileMenuOpen: false,
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
        <a href="{{ route('filament.admin.resources.general-chats.index') }}" class="chat-mobile-back">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 1.5rem; height: 1.5rem;">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
            </svg>
        </a>
        <div class="chat-mobile-title-area">
            <h1 class="chat-mobile-title">{{ $this->chatTitle }}</h1>
            <p class="chat-mobile-subtitle">General Chat</p>
        </div>
        {{-- Context indicator --}}
        <div class="chat-mobile-context" title="{{ $chat->is_compacting ? 'Compacting conversation...' : number_format($this->contextUsed) . ' / ' . number_format($this->contextLimit) . ' tokens' }}">
            @if($chat->is_compacting)
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
            @if($chat->compaction_count > 0)
                <span class="inline-flex items-center justify-center w-5 h-5 text-xs font-medium rounded-full bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300">
                    {{ $chat->compaction_count }}
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
        </div>
    </div>

    {{-- Desktop Header --}}
    <div class="chat-header">
        <div class="chat-header-info">
            <div class="chat-header-title-row">
                <h2 class="chat-header-title">{{ $this->chatTitle }}</h2>
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
                @endif
            </div>
            <p class="chat-header-subtitle">General Chat</p>
        </div>
        <div class="chat-header-controls">
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
            {{-- Context Usage Indicator --}}
            <div
                class="flex items-center gap-2"
                title="{{ $chat->is_compacting ? 'Compacting conversation...' : number_format($this->contextUsed) . ' tokens used of ' . number_format($this->contextLimit) . ' (' . number_format($this->contextPercentage, 1) . '%)' }}"
            >
                @if($chat->is_compacting)
                    {{-- Compacting State --}}
                    <div class="flex items-center gap-1.5 px-2 py-0.5 bg-amber-100 dark:bg-amber-900/30 rounded-full">
                        <svg class="w-3 h-3 text-amber-600 dark:text-amber-400 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span class="text-xs font-medium text-amber-700 dark:text-amber-300">Compacting</span>
                    </div>
                @else
                    {{-- Normal Context Bar --}}
                    <div class="w-24 h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                        <div
                            class="{{ $this->contextColor }} h-full transition-all duration-300"
                            style="width: {{ min($this->contextPercentage, 100) }}%"
                        ></div>
                    </div>
                    <span class="text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">
                        {{ number_format($this->contextUsed / 1000, 0) }}K / {{ number_format($this->contextLimit / 1000, 0) }}K
                    </span>
                @endif
                {{-- Compaction Count Badge --}}
                @if($chat->compaction_count > 0)
                    <span
                        class="inline-flex items-center gap-1 px-1.5 py-0.5 text-xs font-medium rounded-full bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300"
                        title="{{ $chat->compaction_count }} conversation {{ Str::plural('compaction', $chat->compaction_count) }}"
                    >
                        <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 9V4.5M9 9H4.5M9 9L3.75 3.75M9 15v4.5M9 15H4.5M9 15l-5.25 5.25M15 9h4.5M15 9V4.5M15 9l5.25-5.25M15 15h4.5M15 15v4.5m0-4.5l5.25 5.25" />
                        </svg>
                        {{ $chat->compaction_count }}
                    </span>
                @endif
            </div>
        </div>
    </div>

    {{-- Chat Area --}}
    <div class="chat-area"
        x-data="{
            polling: @entangle('waitingForResponse').live,
            isNearBottom: true,
            scrollThreshold: 150,
            checkIfNearBottom() {
                const el = this.$refs.messages;
                this.isNearBottom = (el.scrollHeight - el.scrollTop - el.clientHeight) < this.scrollThreshold;
            },
            scrollToBottom() {
                this.$refs.messages.scrollTop = this.$refs.messages.scrollHeight;
            },
            init() {
                this.scrollToBottom();
                this.$refs.messages.addEventListener('scroll', () => this.checkIfNearBottom());
                const observer = new MutationObserver(() => {
                    this.$nextTick(() => {
                        if (this.isNearBottom) {
                            this.scrollToBottom();
                        }
                    });
                });
                observer.observe(this.$refs.messages, { childList: true, subtree: true });
            }
        }"
    >
        {{-- Messages --}}
        <div class="chat-messages" x-ref="messages" wire:poll.1s="checkPolling">
            @forelse($this->chatMessages as $message)
                <div wire:key="message-{{ $message->id }}" class="chat-message {{ $message->isFromUser() ? 'chat-message-user' : 'chat-message-assistant' }}">
                    <div class="chat-bubble {{ $message->isFromUser() ? 'chat-bubble-user' : 'chat-bubble-assistant' }}">
                        @if($message->isFromUser())
                            @if($message->images && count($message->images) > 0)
                                <div class="chat-message-images">
                                    @foreach($message->images as $index => $image)
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
                                <p style="white-space: pre-wrap; margin: 0;">{{ $message->content }}</p>
                            @endif
                        @else
                            @if($message->content_blocks && count($message->content_blocks) > 0)
                                {{-- Render interleaved content blocks as separate bubbles --}}
                                <div class="chat-bubble-content">
                                    {!! Str::markdown($message->content_blocks[0]['text'] ?? '') !!}
                                </div>
                            @else
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
                            @endif

                            @if($message->tokens_in || $message->tokens_out)
                                <div class="chat-tokens">
                                    {{ number_format($message->tokens_in ?? 0) }} in /
                                    {{ number_format($message->tokens_out ?? 0) }} out
                                    @if($message->cost_usd)
                                        (${{ number_format($message->cost_usd, 4) }})
                                    @endif
                                </div>
                            @endif
                        @endif
                    </div>
                </div>
                {{-- Render remaining content blocks with tool calls grouped --}}
                @if($message->isFromAssistant() && $message->content_blocks && count($message->content_blocks) > 1)
                    @php
                        $blocks = collect($message->content_blocks)->skip(1)->values();
                        $groupedBlocks = [];
                        $currentToolGroup = [];

                        foreach ($blocks as $block) {
                            if (($block['type'] ?? '') === 'tool_use') {
                                $currentToolGroup[] = $block;
                            } else {
                                if (count($currentToolGroup) > 0) {
                                    $groupedBlocks[] = ['type' => 'tool_group', 'tools' => $currentToolGroup];
                                    $currentToolGroup = [];
                                }
                                $groupedBlocks[] = $block;
                            }
                        }
                        if (count($currentToolGroup) > 0) {
                            $groupedBlocks[] = ['type' => 'tool_group', 'tools' => $currentToolGroup];
                        }
                    @endphp

                    @foreach($groupedBlocks as $index => $block)
                        @if(($block['type'] ?? '') === 'text' && !empty($block['text']))
                            <div wire:key="message-{{ $message->id }}-grouped-{{ $index }}" class="chat-message chat-message-assistant">
                                <div class="chat-bubble chat-bubble-assistant">
                                    <div class="chat-bubble-content">
                                        {!! Str::markdown($block['text']) !!}
                                    </div>
                                </div>
                            </div>
                        @elseif(($block['type'] ?? '') === 'tool_group')
                            <div wire:key="message-{{ $message->id }}-grouped-{{ $index }}" class="chat-message chat-message-assistant">
                                <div class="chat-bubble chat-bubble-tool">
                                    @if(count($block['tools']) === 1)
                                        <div class="chat-tool-use">
                                            <span class="chat-tool-use-icon">⚙</span>
                                            <span class="chat-tool-use-name">{{ $block['tools'][0]['tool']['name'] ?? 'Tool' }}</span>
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
                                                    <div class="chat-tool-use" style="margin-bottom: 0.25rem;">
                                                        <span class="chat-tool-use-icon">⚙</span>
                                                        <span class="chat-tool-use-name">{{ $tool['tool']['name'] ?? 'Tool' }}</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif
                    @endforeach
                @endif
            @empty
                <div class="chat-empty">
                    <p>Start a conversation with Claude</p>
                </div>
            @endforelse

            <div x-show="polling" class="chat-thinking">
                <div class="chat-thinking-bubble">
                    <div class="chat-thinking-content">
                        <div class="chat-thinking-dot"></div>
                        <span class="chat-thinking-text">Claude is thinking...</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Input --}}
        <div class="chat-input-area"
            x-data="{
                images: @entangle('images'),
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

            <form wire:submit="sendMessage" class="chat-form">
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

                <div class="chat-input-wrapper">
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

                    <textarea
                        wire:model.live="prompt"
                        placeholder="Type a message..."
                        rows="1"
                        class="chat-textarea"
                        @paste="handlePaste($event)"
                        @keydown.enter.prevent="if (!$event.shiftKey && !$wire.isRunning) $wire.sendMessage()"
                    ></textarea>
                </div>
                <button
                    type="submit"
                    class="chat-submit"
                    @disabled($this->isRunning || (empty($prompt) && empty($images)))
                    title="Send message"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width: 1.25rem; height: 1.25rem;">
                        <path d="M3.478 2.404a.75.75 0 0 0-.926.941l2.432 7.905H13.5a.75.75 0 0 1 0 1.5H4.984l-2.432 7.905a.75.75 0 0 0 .926.94 60.519 60.519 0 0 0 18.445-8.986.75.75 0 0 0 0-1.218A60.517 60.517 0 0 0 3.478 2.404Z" />
                    </svg>
                </button>
            </form>
        </div>
    </div>

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
