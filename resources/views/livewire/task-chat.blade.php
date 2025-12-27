<div class="chat-container">
    {{-- Header --}}
    <div class="chat-header">
        <div class="chat-header-info">
            <div class="chat-header-title-row">
                <h2 class="chat-header-title">{{ $task->title ?? $task->repository->name }}</h2>
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
            <p class="chat-header-subtitle">{{ $this->locationLabel }}</p>
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
                title="{{ number_format($this->contextUsed) }} tokens used of {{ number_format($this->contextLimit) }} ({{ number_format($this->contextPercentage, 1) }}%)"
            >
                <div class="w-24 h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                    <div
                        class="{{ $this->contextColor }} h-full transition-all duration-300"
                        style="width: {{ min($this->contextPercentage, 100) }}%"
                    ></div>
                </div>
                <span class="text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">
                    {{ number_format($this->contextUsed / 1000, 0) }}K / {{ number_format($this->contextLimit / 1000, 0) }}K
                </span>
            </div>
            @if($task->isInWorkspace())
                <div class="chat-action-buttons">
                    @if($this->hasEnvConfigs)
                        <div x-data="{ open: false }" class="relative">
                            <div class="inline-flex rounded-lg shadow-sm">
                                <button
                                    type="button"
                                    wire:click="copyEnvConfig"
                                    class="inline-flex items-center gap-1 rounded-l-lg bg-gray-100 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                                >
                                    <x-heroicon-o-document-duplicate style="width: 1rem; height: 1rem;" />
                                    Copy .env
                                </button>
                                <button
                                    type="button"
                                    @click="open = !open"
                                    class="inline-flex items-center rounded-r-lg border-l border-gray-300 bg-gray-100 px-2 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                                >
                                    <x-heroicon-o-chevron-down style="width: 1rem; height: 1rem;" />
                                </button>
                            </div>

                            <div
                                x-show="open"
                                @click.away="open = false"
                                x-transition
                                class="absolute right-0 z-10 mt-1 w-48 origin-top-right rounded-lg bg-white shadow-lg ring-1 ring-black ring-opacity-5 dark:bg-gray-800 dark:ring-gray-700"
                            >
                                <div class="py-1">
                                    @foreach($this->envConfigs as $config)
                                        <button
                                            type="button"
                                            wire:click="copyEnvConfig({{ $config->id }})"
                                            @click="open = false"
                                            class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700"
                                        >
                                            @if($config->is_default)
                                                <x-heroicon-o-star style="width: 1rem; height: 1rem; color: #eab308;" />
                                            @else
                                                <span style="width: 1rem; height: 1rem; display: inline-block;"></span>
                                            @endif
                                            {{ $config->name }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif
                    <button wire:click="openDeployModal" class="chat-action-btn chat-action-btn-success">
                        Deploy to Site
                    </button>
                    <button
                        wire:click="deleteWorkspace"
                        wire:confirm="Are you sure you want to delete this workspace? This cannot be undone."
                        class="chat-action-btn chat-action-btn-danger"
                    >
                        Delete Workspace
                    </button>
                </div>
            @endif
        </div>
    </div>

    {{-- Chat Area --}}
    <div class="chat-area"
        x-data="{
            polling: @entangle('waitingForResponse').live,
            scrollToBottom() {
                this.$refs.messages.scrollTop = this.$refs.messages.scrollHeight;
            },
            init() {
                this.scrollToBottom();
                const observer = new MutationObserver(() => this.$nextTick(() => this.scrollToBottom()));
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
                {{-- Render remaining content blocks as separate bubbles --}}
                @if($message->isFromAssistant() && $message->content_blocks && count($message->content_blocks) > 1)
                    @foreach($message->content_blocks as $index => $block)
                        @if($index === 0)
                            @continue
                        @endif
                        @if(($block['type'] ?? '') === 'text' && !empty($block['text']))
                            <div wire:key="message-{{ $message->id }}-block-{{ $index }}" class="chat-message chat-message-assistant">
                                <div class="chat-bubble chat-bubble-assistant">
                                    <div class="chat-bubble-content">
                                        {!! Str::markdown($block['text']) !!}
                                    </div>
                                </div>
                            </div>
                        @elseif(($block['type'] ?? '') === 'tool_use')
                            <div wire:key="message-{{ $message->id }}-block-{{ $index }}" class="chat-message chat-message-assistant">
                                <div class="chat-bubble chat-bubble-tool">
                                    <div class="chat-tool-use">
                                        <span class="chat-tool-use-icon">⚙</span>
                                        <span class="chat-tool-use-name">{{ $block['tool']['name'] ?? 'Tool' }}</span>
                                    </div>
                                </div>
                            </div>
                        @endif
                    @endforeach
                @endif
            @empty
                <div class="chat-empty">
                    <p>Start a conversation with Claude Code</p>
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
                                const reader = new FileReader();
                                reader.onload = (event) => {
                                    this.images.push({
                                        data: event.target.result,
                                        name: file.name || 'pasted-image.png'
                                    });
                                };
                                reader.readAsDataURL(file);
                            }
                        }
                    }
                },
                removeImage(index) {
                    this.images.splice(index, 1);
                }
            }"
        >
            <form wire:submit="sendMessage" class="chat-form">
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
                    @disabled($this->isRunning || empty($prompt))
                    title="Send message"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width: 1.25rem; height: 1.25rem;">
                        <path d="M3.478 2.404a.75.75 0 0 0-.926.941l2.432 7.905H13.5a.75.75 0 0 1 0 1.5H4.984l-2.432 7.905a.75.75 0 0 0 .926.94 60.519 60.519 0 0 0 18.445-8.986.75.75 0 0 0 0-1.218A60.517 60.517 0 0 0 3.478 2.404Z" />
                    </svg>
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
