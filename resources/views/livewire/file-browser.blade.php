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
        <div
            wire:click.self="closePreview"
            style="position: fixed; inset: 0; z-index: 9999; display: flex; align-items: center; justify-content: center; background-color: rgba(0, 0, 0, 0.5);"
        >
            <div style="display: flex; flex-direction: column; max-height: 80vh; width: 100%; max-width: 56rem; overflow: hidden; border-radius: 0.5rem; background-color: white;">
                <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #e5e7eb; padding: 1rem;">
                    <h3 style="font-family: monospace; font-size: 0.875rem;">{{ $selectedFile }}</h3>
                    <button wire:click="closePreview" style="color: #6b7280; cursor: pointer;">
                        <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div style="flex: 1; overflow: auto; padding: 1rem;">
                    <pre style="white-space: pre-wrap; font-family: monospace; font-size: 0.75rem;">{{ $fileContent }}</pre>
                </div>
            </div>
        </div>
    @endif
</div>
