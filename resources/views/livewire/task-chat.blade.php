<div class="flex h-[calc(100vh-12rem)] flex-col">
    {{-- Header --}}
    <div class="mb-4 flex items-center justify-between rounded-lg bg-white p-4 shadow dark:bg-gray-900">
        <div>
            <h2 class="text-lg font-semibold">{{ $task->repository->name }}</h2>
            <p class="text-sm text-gray-500">{{ $this->locationLabel }}</p>
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
        <div class="flex gap-2">
            @if($task->isInWorkspace())
                <button
                    wire:click="openDeployModal"
                    class="rounded-lg bg-green-600 px-4 py-2 text-sm text-white hover:bg-green-700"
                >
                    Deploy to Site
                </button>
                <button
                    wire:click="deleteWorkspace"
                    wire:confirm="Are you sure you want to delete this workspace? This cannot be undone."
                    class="rounded-lg bg-red-600 px-4 py-2 text-sm text-white hover:bg-red-700"
                >
                    Delete Workspace
                </button>
            @endif
        </div>
    </div>

    {{-- Chat Area --}}
    <div class="flex flex-1 flex-col rounded-lg bg-white shadow dark:bg-gray-900">
        {{-- Messages --}}
        <div class="flex-1 overflow-y-auto p-4 space-y-4" @if($this->isRunning) wire:poll.2s="$refresh" @endif>
            @forelse($this->chatMessages as $message)
                <div wire:key="message-{{ $message->id }}" @class([
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

    {{-- Deploy Modal --}}
    @if($showDeployModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
        <div class="w-full max-w-md rounded-lg bg-white p-6 dark:bg-gray-800">
            <h3 class="mb-4 text-lg font-semibold">Deploy to Site</h3>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium">Subdomain</label>
                    <div class="mt-1 flex">
                        <input
                            type="text"
                            wire:model="deploySubdomain"
                            class="flex-1 rounded-l-lg border px-3 py-2 dark:bg-gray-700"
                            placeholder="my-feature"
                        >
                        <span class="rounded-r-lg border border-l-0 bg-gray-100 px-3 py-2 dark:bg-gray-600">.marin.sh</span>
                    </div>
                </div>

                <div class="text-sm text-gray-500">
                    <p>Preview:</p>
                    <ul class="ml-4 list-disc">
                        <li>Branch: {{ $deploySubdomain ?: 'subdomain' }}</li>
                        <li>PHP: {{ $deployPhpVersion }}</li>
                        <li>Web directory: {{ $deployWebDirectory }}</li>
                    </ul>
                </div>

                <button
                    type="button"
                    wire:click="$toggle('showAdvancedOptions')"
                    class="text-sm text-primary-600"
                >
                    {{ $showAdvancedOptions ? '▼' : '▶' }} Advanced Options
                </button>

                @if($showAdvancedOptions)
                    <div class="space-y-3 border-t pt-3">
                        <div>
                            <label class="block text-sm font-medium">PHP Version</label>
                            <select wire:model="deployPhpVersion" class="mt-1 w-full rounded-lg border px-3 py-2 dark:bg-gray-700">
                                <option value="8.4">8.4</option>
                                <option value="8.3">8.3</option>
                                <option value="8.2">8.2</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium">Web Directory</label>
                            <input type="text" wire:model="deployWebDirectory" class="mt-1 w-full rounded-lg border px-3 py-2 dark:bg-gray-700">
                        </div>
                        <div>
                            <label class="block text-sm font-medium">Database Name (optional)</label>
                            <input type="text" wire:model="deployDatabaseName" class="mt-1 w-full rounded-lg border px-3 py-2 dark:bg-gray-700">
                        </div>
                    </div>
                @endif
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <button
                    wire:click="closeDeployModal"
                    class="rounded-lg border px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-700"
                >
                    Cancel
                </button>
                <button
                    wire:click="deployToSite"
                    class="rounded-lg bg-green-600 px-4 py-2 text-white hover:bg-green-700"
                >
                    Deploy
                </button>
            </div>
        </div>
    </div>
    @endif
</div>
