<div class="chat-container">
    {{-- Header --}}
    <div class="chat-header">
        <div class="chat-header-info">
            <h2 class="chat-header-title">{{ $task->repository->name }}</h2>
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
            @if($task->isInWorkspace())
                <div class="chat-action-buttons">
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
        <div class="chat-messages" x-ref="messages" @if($this->shouldPoll) wire:poll.1s="$refresh" @endif>
            @forelse($this->chatMessages as $message)
                <div wire:key="message-{{ $message->id }}" class="chat-message {{ $message->isFromUser() ? 'chat-message-user' : 'chat-message-assistant' }}">
                    <div class="chat-bubble {{ $message->isFromUser() ? 'chat-bubble-user' : 'chat-bubble-assistant' }}">
                        @if($message->isFromUser())
                            <p style="white-space: pre-wrap; margin: 0;">{{ $message->content }}</p>
                        @else
                            <div class="chat-bubble-content">
                                {!! Str::markdown($message->content ?? '') !!}
                            </div>

                            @if($message->tool_calls)
                                <div class="chat-tool-calls">
                                    @foreach($message->tool_calls as $tool)
                                        <details class="chat-tool-call">
                                            <summary>{{ $tool['name'] ?? 'Tool' }}</summary>
                                            <pre>{{ json_encode($tool['input'] ?? [], JSON_PRETTY_PRINT) }}</pre>
                                        </details>
                                    @endforeach
                                </div>
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
            @empty
                <div class="chat-empty">
                    <p>Start a conversation with Claude Code</p>
                </div>
            @endforelse

            @if($this->shouldPoll)
                <div class="chat-thinking">
                    <div class="chat-thinking-bubble">
                        <div class="chat-thinking-content">
                            <div class="chat-thinking-dot"></div>
                            <span class="chat-thinking-text">Claude is thinking...</span>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{-- Input --}}
        <div class="chat-input-area">
            <form wire:submit="sendMessage" class="chat-form">
                <textarea
                    wire:model.live="prompt"
                    placeholder="Type your message..."
                    rows="2"
                    class="chat-textarea"
                    @disabled($this->isRunning)
                ></textarea>
                <button
                    type="submit"
                    class="chat-submit"
                    @disabled($this->isRunning || empty($prompt))
                >
                    Send
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
</div>
