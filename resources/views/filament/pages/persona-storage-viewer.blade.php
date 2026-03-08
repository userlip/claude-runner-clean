<div class="space-y-4">
    @if(empty($tree))
        <p class="text-sm text-gray-500 dark:text-gray-400">No storage files found.</p>
    @else
        <div class="space-y-2">
            @include('filament.pages.partials.storage-tree', ['items' => $tree, 'persona' => $persona, 'storageService' => $storageService, 'depth' => 0])
        </div>
    @endif
</div>
