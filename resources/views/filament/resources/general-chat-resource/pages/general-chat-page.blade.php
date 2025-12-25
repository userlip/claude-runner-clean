<x-filament-panels::page>
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; height: calc(100vh - 16rem);">
        {{-- Chat Area (2/3 width) --}}
        <div style="height: 100%;">
            @livewire('general-chat-box', ['chat' => $this->getRecord()])
        </div>

        {{-- File Browser (1/3 width) --}}
        <div style="height: 100%;">
            @livewire('file-browser', ['basePath' => $this->getRecord()->working_directory])
        </div>
    </div>
</x-filament-panels::page>
