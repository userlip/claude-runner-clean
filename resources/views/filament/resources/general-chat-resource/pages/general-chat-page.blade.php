<x-filament-panels::page>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 h-[calc(100vh-16rem)]">
        {{-- Chat Area (2/3 width on large screens) --}}
        <div class="lg:col-span-2 h-full">
            @livewire('general-chat-box', ['chat' => $this->getRecord()])
        </div>

        {{-- File Browser (1/3 width on large screens) --}}
        <div class="h-full hidden lg:block">
            @livewire('file-browser', ['basePath' => $this->getRecord()->working_directory])
        </div>
    </div>
</x-filament-panels::page>
