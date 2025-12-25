<div class="flex flex-col h-full">
    {{-- Header --}}
    <div class="mb-4 flex items-center justify-between rounded-lg bg-white dark:bg-gray-800 p-4 shadow">
        <div>
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $this->chatTitle }}</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $chat->working_directory }}</p>
        </div>
    </div>

    {{-- Chat Area --}}
    <div class="flex flex-col flex-1 rounded-lg bg-white dark:bg-gray-800 shadow" style="min-height: 400px;">
        {{-- Messages --}}
        <div class="flex-1 overflow-y-auto p-4" @if($this->shouldPoll) wire:poll.1s="$refresh" @endif>
            @forelse($this->chatMessages as $message)
                <div wire:key="message-{{ $message->id }}" class="flex mb-4 {{ $message->isFromUser() ? 'justify-end' : 'justify-start' }}">
                    <div class="max-w-[80%] rounded-lg px-4 py-2 {{ $message->isFromUser() ? 'bg-blue-600 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-gray-100' }}">
                        @if($message->isFromUser())
                            <p class="whitespace-pre-wrap m-0">{{ $message->content }}</p>
                        @else
                            <div class="text-sm prose dark:prose-invert max-w-none">
                                {!! Str::markdown($message->content ?? '') !!}
                            </div>

                            @if($message->tool_calls)
                                <div class="mt-2">
                                    @foreach($message->tool_calls as $tool)
                                        <details class="rounded bg-gray-200 dark:bg-gray-600 p-2 text-xs mb-2">
                                            <summary class="cursor-pointer font-mono text-gray-700 dark:text-gray-200">{{ $tool['name'] ?? 'Tool' }}</summary>
                                            <pre class="mt-1 overflow-x-auto text-[10px] text-gray-600 dark:text-gray-300">{{ json_encode($tool['input'] ?? [], JSON_PRETTY_PRINT) }}</pre>
                                        </details>
                                    @endforeach
                                </div>
                            @endif

                            @if($message->tokens_in || $message->tokens_out)
                                <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">
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
                <div class="flex h-full items-center justify-center text-gray-500 dark:text-gray-400" style="min-height: 300px;">
                    <p>Start a conversation with Claude</p>
                </div>
            @endforelse

            @if($this->isRunning)
                <div class="flex justify-start mb-4">
                    <div class="rounded-lg bg-gray-100 dark:bg-gray-700 px-4 py-2">
                        <div class="flex items-center gap-2">
                            <div class="w-2 h-2 rounded-full bg-blue-500 animate-pulse"></div>
                            <span class="text-sm text-gray-500 dark:text-gray-400">Claude is thinking...</span>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{-- Input --}}
        <div class="border-t border-gray-200 dark:border-gray-700 p-4">
            <form wire:submit="sendMessage" class="flex gap-2">
                <textarea
                    wire:model.live="prompt"
                    placeholder="Type your message..."
                    rows="2"
                    class="flex-1 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white px-4 py-2 text-sm resize-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    @disabled($this->isRunning)
                ></textarea>
                <button
                    type="submit"
                    class="rounded-lg bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 dark:disabled:bg-gray-600 px-4 py-2 text-white font-medium cursor-pointer border-none transition-colors"
                    @disabled($this->isRunning || empty($prompt))
                >
                    Send
                </button>
            </form>
        </div>
    </div>
</div>
