<div style="display: flex; flex-direction: column; height: 100%; border-radius: 0.5rem; background-color: white; box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);">
    <div style="border-bottom: 1px solid #e5e7eb; padding: 0.75rem;">
        <h3 style="font-size: 0.875rem; font-weight: 600; margin: 0;">Files</h3>
        <p style="font-size: 0.75rem; color: #6b7280; margin: 0.25rem 0 0 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ $basePath }}</p>
    </div>

    <div style="flex: 1; overflow-y: auto; padding: 0.5rem;">
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
                    <h3 style="font-family: monospace; font-size: 0.875rem; margin: 0;">{{ $selectedFile }}</h3>
                    <button wire:click="closePreview" style="color: #6b7280; cursor: pointer; background: none; border: none; padding: 0.25rem;">
                        <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div style="flex: 1; overflow: auto; padding: 1rem;">
                    <pre style="white-space: pre-wrap; font-family: monospace; font-size: 0.75rem; margin: 0;">{{ $fileContent }}</pre>
                </div>
            </div>
        </div>
    @endif
</div>
