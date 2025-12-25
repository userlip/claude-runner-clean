@php
    $paddingLeft = $depth * 1;
@endphp

<div style="padding-left: {{ $paddingLeft }}rem;">
    @if($item['isDir'])
        <button
            wire:click="toggleDirectory('{{ $item['path'] }}')"
            class="flex w-full items-center gap-2 rounded px-2 py-1 text-left text-sm hover:bg-gray-100 dark:hover:bg-gray-800"
            style="display: flex; align-items: center; gap: 0.5rem;"
        >
            @if($this->isExpanded($item['path']))
                <svg width="16" height="16" class="shrink-0 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9.776c.112-.017.227-.026.344-.026h15.812c.117 0 .232.009.344.026m-16.5 0a2.25 2.25 0 00-1.883 2.542l.857 6a2.25 2.25 0 002.227 1.932H19.05a2.25 2.25 0 002.227-1.932l.857-6a2.25 2.25 0 00-1.883-2.542m-16.5 0V6A2.25 2.25 0 016 3.75h3.879a1.5 1.5 0 011.06.44l2.122 2.12a1.5 1.5 0 001.06.44H18A2.25 2.25 0 0120.25 9v.776" />
                </svg>
            @else
                <svg width="16" height="16" class="shrink-0 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" />
                </svg>
            @endif
            <span>{{ $item['name'] }}</span>
        </button>

        @if($this->isExpanded($item['path']))
            @foreach($this->getFilesInDirectory($basePath . '/' . $item['path']) as $child)
                @include('livewire.partials.file-browser-item', ['item' => $child, 'depth' => $depth + 1])
            @endforeach
        @endif
    @else
        <button
            wire:click="selectFile('{{ $item['path'] }}')"
            class="flex w-full items-center gap-2 rounded px-2 py-1 text-left text-sm hover:bg-gray-100 dark:hover:bg-gray-800"
            style="display: flex; align-items: center; gap: 0.5rem;"
        >
            <svg width="16" height="16" class="shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
            </svg>
            <span class="truncate">{{ $item['name'] }}</span>
        </button>
    @endif
</div>
