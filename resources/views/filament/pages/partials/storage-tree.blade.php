@foreach($items as $item)
    <div style="margin-left: {{ $depth * 1.5 }}rem;">
        @if($item['type'] === 'directory')
            <div class="flex items-center gap-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                <x-heroicon-o-folder class="h-4 w-4 text-yellow-500" />
                {{ $item['name'] }}/
            </div>
            @if(!empty($item['children']))
                @include('filament.pages.partials.storage-tree', ['items' => $item['children'], 'persona' => $persona, 'storageService' => $storageService, 'depth' => $depth + 1])
            @endif
        @else
            @php
                $fileContent = $storageService->readFile($persona, $item['path']);
            @endphp
            <details class="group">
                <summary class="flex items-center gap-1 text-sm text-gray-600 dark:text-gray-400 cursor-pointer hover:text-gray-900 dark:hover:text-gray-200">
                    <x-heroicon-o-document class="h-4 w-4 text-gray-400" />
                    {{ $item['name'] }}
                </summary>
                @if($fileContent !== null)
                    <div class="mt-1 rounded-lg bg-gray-50 dark:bg-gray-800 p-3 text-xs font-mono whitespace-pre-wrap text-gray-700 dark:text-gray-300 max-h-64 overflow-y-auto">{{ $fileContent }}</div>
                @endif
            </details>
        @endif
    </div>
@endforeach
