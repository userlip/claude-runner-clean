<div style="display: flex; flex-direction: column; height: 100%;">
    {{-- Header --}}
    <div style="margin-bottom: 1rem; display: flex; align-items: center; justify-content: space-between; border-radius: 0.5rem; background-color: white; padding: 1rem; box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);">
        <div>
            <h2 style="font-size: 1.125rem; font-weight: 600;">{{ $this->chatTitle }}</h2>
            <p style="font-size: 0.875rem; color: #6b7280;">{{ $chat->working_directory }}</p>
        </div>
    </div>

    {{-- Chat Area --}}
    <div style="display: flex; flex-direction: column; flex: 1; border-radius: 0.5rem; background-color: white; box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1); min-height: 400px;">
        {{-- Messages --}}
        <div style="flex: 1; overflow-y: auto; padding: 1rem;" @if($this->shouldPoll) wire:poll.1s="$refresh" @endif>
            @forelse($this->chatMessages as $message)
                <div wire:key="message-{{ $message->id }}" style="display: flex; margin-bottom: 1rem; {{ $message->isFromUser() ? 'justify-content: flex-end;' : 'justify-content: flex-start;' }}">
                    <div style="max-width: 80%; border-radius: 0.5rem; padding: 0.5rem 1rem; {{ $message->isFromUser() ? 'background-color: #2563eb; color: white;' : 'background-color: #f3f4f6;' }}">
                        @if($message->isFromUser())
                            <p style="white-space: pre-wrap; margin: 0;">{{ $message->content }}</p>
                        @else
                            <div style="font-size: 0.875rem;">
                                {!! Str::markdown($message->content ?? '') !!}
                            </div>

                            @if($message->tool_calls)
                                <div style="margin-top: 0.5rem;">
                                    @foreach($message->tool_calls as $tool)
                                        <details style="border-radius: 0.25rem; background-color: #e5e7eb; padding: 0.5rem; font-size: 0.75rem; margin-bottom: 0.5rem;">
                                            <summary style="cursor: pointer; font-family: monospace;">{{ $tool['name'] ?? 'Tool' }}</summary>
                                            <pre style="margin-top: 0.25rem; overflow-x: auto; font-size: 0.7rem;">{{ json_encode($tool['input'] ?? [], JSON_PRETTY_PRINT) }}</pre>
                                        </details>
                                    @endforeach
                                </div>
                            @endif

                            @if($message->tokens_in || $message->tokens_out)
                                <div style="margin-top: 0.5rem; font-size: 0.75rem; color: #6b7280;">
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
                <div style="display: flex; height: 100%; min-height: 300px; align-items: center; justify-content: center; color: #6b7280;">
                    <p>Start a conversation with Claude</p>
                </div>
            @endforelse

            @if($this->isRunning)
                <div style="display: flex; justify-content: flex-start; margin-bottom: 1rem;">
                    <div style="border-radius: 0.5rem; background-color: #f3f4f6; padding: 0.5rem 1rem;">
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <div style="width: 0.5rem; height: 0.5rem; border-radius: 50%; background-color: #3b82f6; animation: pulse 2s infinite;"></div>
                            <span style="font-size: 0.875rem; color: #6b7280;">Claude is thinking...</span>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{-- Input --}}
        <div style="border-top: 1px solid #e5e7eb; padding: 1rem;">
            <form wire:submit="sendMessage" style="display: flex; gap: 0.5rem;">
                <textarea
                    wire:model.live="prompt"
                    placeholder="Type your message..."
                    rows="2"
                    style="flex: 1; border-radius: 0.5rem; border: 1px solid #d1d5db; padding: 0.5rem 1rem; font-size: 0.875rem; resize: none;"
                    @disabled($this->isRunning)
                ></textarea>
                <button
                    type="submit"
                    style="border-radius: 0.5rem; background-color: #2563eb; padding: 0.5rem 1rem; color: white; font-weight: 500; cursor: pointer; border: none;"
                    @disabled($this->isRunning || empty($prompt))
                >
                    Send
                </button>
            </form>
        </div>
    </div>
</div>
