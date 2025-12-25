<div class="flex h-full flex-col rounded-lg bg-white shadow dark:bg-gray-900">
    <div class="border-b p-3 dark:border-gray-700">
        <h3 class="text-sm font-semibold">Files</h3>
        <p class="truncate text-xs text-gray-500">{{ $basePath }}</p>
    </div>

    <div class="flex-1 overflow-y-auto p-2">
        @foreach($this->files as $item)
            @include('livewire.partials.file-browser-item', ['item' => $item, 'depth' => 0])
        @endforeach
    </div>

    {{-- File Preview Modal --}}
    @if($selectedFile)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" wire:click.self="closePreview">
            <div class="flex max-h-[80vh] w-full max-w-4xl flex-col overflow-hidden rounded-lg bg-white dark:bg-gray-800">
                <div class="flex items-center justify-between border-b p-4 dark:border-gray-700">
                    <h3 class="font-mono text-sm">{{ $selectedFile }}</h3>
                    <button wire:click="closePreview" class="text-gray-500 hover:text-gray-700">
                        <x-heroicon-o-x-mark class="h-5 w-5" />
                    </button>
                </div>
                <div class="flex-1 overflow-auto p-4">
                    <pre class="whitespace-pre-wrap font-mono text-xs">{{ $fileContent }}</pre>
                </div>
            </div>
        </div>
    @endif
</div>
