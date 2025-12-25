<div class="flex flex-col h-full rounded-lg bg-white dark:bg-gray-800 shadow">
    <div class="border-b border-gray-200 dark:border-gray-700 px-3 py-3">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-white m-0">Files</h3>
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 m-0 overflow-hidden text-ellipsis whitespace-nowrap">{{ $basePath }}</p>
    </div>

    <div class="flex-1 overflow-y-auto p-2">
        @foreach($this->files as $item)
            @include('livewire.partials.file-browser-item', ['item' => $item, 'depth' => 0])
        @endforeach
    </div>

    {{-- File Preview Modal --}}
    @if($selectedFile)
        <div
            wire:click.self="closePreview"
            class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/50"
        >
            <div class="flex flex-col max-h-[80vh] w-full max-w-4xl overflow-hidden rounded-lg bg-white dark:bg-gray-800">
                <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-700 p-4">
                    <h3 class="font-mono text-sm text-gray-900 dark:text-white m-0">{{ $selectedFile }}</h3>
                    <button wire:click="closePreview" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 cursor-pointer bg-transparent border-none p-1">
                        <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="flex-1 overflow-auto p-4">
                    <pre class="whitespace-pre-wrap font-mono text-xs text-gray-800 dark:text-gray-200 m-0">{{ $fileContent }}</pre>
                </div>
            </div>
        </div>
    @endif
</div>
