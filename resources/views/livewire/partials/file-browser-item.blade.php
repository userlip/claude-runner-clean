@php
    $paddingLeft = $depth * 1;
@endphp

<div style="padding-left: {{ $paddingLeft }}rem;">
    @if($item['isDir'])
        <button
            wire:click="toggleDirectory('{{ $item['path'] }}')"
            class="flex w-full items-center gap-2 rounded px-2 py-1 text-left text-sm hover:bg-gray-100 dark:hover:bg-gray-800"
        >
            @if($this->isExpanded($item['path']))
                <x-heroicon-o-folder-open class="h-4 w-4 text-yellow-500" />
            @else
                <x-heroicon-o-folder class="h-4 w-4 text-yellow-500" />
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
        >
            <x-dynamic-component :component="$this->getFileIcon($item['extension'] ?? '')" class="h-4 w-4 text-gray-400" />
            <span class="truncate">{{ $item['name'] }}</span>
        </button>
    @endif
</div>
