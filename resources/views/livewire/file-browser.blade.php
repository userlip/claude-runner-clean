<div class="file-browser">
    <div class="file-browser-header">
        <h3 class="file-browser-title">Files</h3>
        <p class="file-browser-path">{{ $basePath }}</p>
    </div>

    <div class="file-browser-list">
        @foreach($this->files as $item)
            @include('livewire.partials.file-browser-item', ['item' => $item, 'depth' => 0])
        @endforeach
    </div>

    {{-- File Preview Modal --}}
    @if($selectedFile)
        <div wire:click.self="closePreview" class="file-preview-overlay">
            <div class="file-preview-modal">
                <div class="file-preview-header">
                    <h3 class="file-preview-title">{{ $selectedFile }}</h3>
                    <button wire:click="closePreview" class="file-preview-close">
                        <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="file-preview-content">
                    <pre class="file-preview-code">{{ $fileContent }}</pre>
                </div>
            </div>
        </div>
    @endif
</div>
