<div class="flex h-[calc(100vh-12rem)] gap-4">
    {{-- Task Sidebar --}}
    <div class="w-64 shrink-0 overflow-y-auto rounded-lg bg-white p-4 shadow dark:bg-gray-900">
        <button
            wire:click="newChat"
            class="mb-4 w-full rounded-lg bg-primary-600 px-4 py-2 text-white hover:bg-primary-700"
        >
            New Chat
        </button>

        <div class="space-y-2">
            @foreach($this->tasks as $task)
                <button
                    wire:click="selectTask({{ $task->id }})"
                    @class([
                        'w-full rounded-lg px-3 py-2 text-left text-sm',
                        'bg-primary-100 dark:bg-primary-900' => $activeTask?->id === $task->id,
                        'hover:bg-gray-100 dark:hover:bg-gray-800' => $activeTask?->id !== $task->id,
                    ])
                >
                    <div class="flex items-center justify-between">
                        <span class="truncate">{{ Str::limit($task->messages()->first()?->content ?? 'New chat', 25) }}</span>
                        <span @class([
                            'h-2 w-2 rounded-full',
                            'bg-green-500' => $task->status === \App\Enums\TaskStatus::Completed,
                            'bg-blue-500 animate-pulse' => $task->status === \App\Enums\TaskStatus::Running,
                            'bg-gray-400' => $task->status === \App\Enums\TaskStatus::Pending,
                            'bg-red-500' => $task->status === \App\Enums\TaskStatus::Failed,
                        ])></span>
                    </div>
                    <div class="text-xs text-gray-500">{{ $task->created_at->diffForHumans() }}</div>
                </button>
            @endforeach
        </div>
    </div>

    {{-- Chat Area --}}
    <div class="flex flex-1 flex-col rounded-lg bg-white shadow dark:bg-gray-900">
        {{-- Messages --}}
        <div class="flex-1 overflow-y-auto p-4 space-y-4" wire:poll.2s="$refresh">
            @forelse($this->chatMessages as $message)
                <div @class([
                    'flex',
                    'justify-end' => $message->isFromUser(),
                    'justify-start' => $message->isFromAssistant(),
                ])>
                    <div @class([
                        'max-w-[80%] rounded-lg px-4 py-2',
                        'bg-primary-600 text-white' => $message->isFromUser(),
                        'bg-gray-100 dark:bg-gray-800' => $message->isFromAssistant(),
                    ])>
                        @if($message->isFromUser())
                            <p class="whitespace-pre-wrap">{{ $message->content }}</p>
                        @else
                            <div class="prose prose-sm dark:prose-invert max-w-none">
                                {!! Str::markdown($message->content ?? '') !!}
                            </div>

                            @if($message->tool_calls)
                                <div class="mt-2 space-y-2">
                                    @foreach($message->tool_calls as $tool)
                                        <details class="rounded bg-gray-200 dark:bg-gray-700 p-2 text-xs">
                                            <summary class="cursor-pointer font-mono">{{ $tool['name'] ?? 'Tool' }}</summary>
                                            <pre class="mt-1 overflow-x-auto">{{ json_encode($tool['input'] ?? [], JSON_PRETTY_PRINT) }}</pre>
                                        </details>
                                    @endforeach
                                </div>
                            @endif

                            @if($message->tokens_in || $message->tokens_out)
                                <div class="mt-2 text-xs text-gray-500">
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
                <div class="flex h-full items-center justify-center text-gray-500">
                    <p>Start a conversation with Claude Code</p>
                </div>
            @endforelse

            @if($this->isRunning)
                <div class="flex justify-start">
                    <div class="rounded-lg bg-gray-100 px-4 py-2 dark:bg-gray-800">
                        <div class="flex items-center gap-2">
                            <div class="h-2 w-2 animate-pulse rounded-full bg-blue-500"></div>
                            <span class="text-sm text-gray-500">Claude is thinking...</span>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{-- Input --}}
        <div class="border-t p-4 dark:border-gray-700">
            <form wire:submit="sendMessage" class="flex gap-2">
                <textarea
                    wire:model="prompt"
                    placeholder="Type your message..."
                    rows="2"
                    class="flex-1 rounded-lg border border-gray-300 px-4 py-2 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800"
                    @disabled($this->isRunning)
                ></textarea>
                <button
                    type="submit"
                    class="rounded-lg bg-primary-600 px-4 py-2 text-white hover:bg-primary-700 disabled:opacity-50"
                    @disabled($this->isRunning || empty($prompt))
                >
                    Send
                </button>
            </form>
        </div>
    </div>
</div>
