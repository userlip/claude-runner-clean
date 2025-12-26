<div class="chat-container">
    {{-- Header --}}
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
                        class="chat-generate-title-btn"
                    >
                        <span wire:loading.remove wire:target="generateTitle">Name</span>
                        <span wire:loading wire:target="generateTitle">...</span>
                    </button>
                @endif
            </div>
            <p class="chat-header-subtitle">{{ $chat->working_directory }}</p>
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
        </div>
    </div>

    {{-- Chat Area --}}
    <div class="chat-area">
        {{-- Messages --}}
        <div class="chat-messages" @if($this->shouldPoll) wire:poll.1s="$refresh" @endif>
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
                    <p>Start a conversation with Claude</p>
                </div>
            @endforelse

            @if($this->isRunning)
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
            @error('prompt')
                <div style="color: rgb(220 38 38); font-size: 0.875rem; margin-bottom: 0.5rem;">
                    {{ $message }}
                </div>
            @enderror
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
</div>
