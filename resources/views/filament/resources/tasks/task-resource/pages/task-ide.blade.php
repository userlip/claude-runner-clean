<x-filament-panels::page>
    <div
        class="ide-page-layout"
        x-data="{
            editorWidth: 70,
            isDragging: false,
            startX: 0,
            startWidth: 0,
            startDrag(e) {
                this.isDragging = true;
                this.startX = e.clientX;
                this.startWidth = this.editorWidth;
                document.body.style.cursor = 'col-resize';
                document.body.style.userSelect = 'none';
            },
            onDrag(e) {
                if (!this.isDragging) return;
                const container = this.$el;
                const containerWidth = container.offsetWidth;
                const delta = e.clientX - this.startX;
                const deltaPercent = (delta / containerWidth) * 100;
                this.editorWidth = Math.min(85, Math.max(30, this.startWidth + deltaPercent));
            },
            stopDrag() {
                this.isDragging = false;
                document.body.style.cursor = '';
                document.body.style.userSelect = '';
            }
        }"
        x-init="document.body.classList.add('ide-immersive-mode')"
        x-on:mousemove.window="onDrag($event)"
        x-on:mouseup.window="stopDrag()"
    >
        {{-- Editor Panel --}}
        <div class="ide-editor-panel" :style="'width: ' + editorWidth + '%'">
            <div class="ide-editor-header">
                <a href="{{ \App\Filament\Resources\Tasks\Pages\TaskChat::getUrl(['record' => $this->getRecord()]) }}" class="ide-back-link">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="ide-back-icon">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                    </svg>
                    Back to Chat
                </a>
                <span class="ide-header-title">{{ $this->getRecord()->title ?? 'Task #' . $this->getRecord()->id }}</span>
            </div>
            <iframe
                src="{{ $this->getIdeUrl() }}"
                class="ide-frame"
                allow="clipboard-read; clipboard-write"
            ></iframe>
        </div>

        {{-- Resize Handle --}}
        <div
            class="ide-resize-handle"
            x-on:mousedown.prevent="startDrag($event)"
            :class="{ 'ide-resize-handle-active': isDragging }"
        ></div>

        {{-- Chat Panel --}}
        <div class="ide-chat-panel" :style="'width: ' + (100 - editorWidth) + '%'">
            @livewire('task-chat', ['task' => $this->getRecord()])
        </div>
    </div>
</x-filament-panels::page>
