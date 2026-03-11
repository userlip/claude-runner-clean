<div class="space-y-3">
    @forelse($historyFiles as $file)
        <details class="group rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
            <summary class="flex items-center justify-between px-4 py-3 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800 rounded-xl">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-document-text class="h-4 w-4 text-gray-400" />
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $file['name'] }}</span>
                </div>
                <span class="text-xs text-gray-500 dark:text-gray-400">{{ $file['date'] }}</span>
            </summary>
            <div class="border-t border-gray-200 dark:border-gray-700 px-4 py-3">
                <div class="prose dark:prose-invert max-w-none text-sm">
                    {!! Str::markdown($file['content']) !!}
                </div>
            </div>
        </details>
    @empty
        <p class="text-sm text-gray-500 dark:text-gray-400">No run history available.</p>
    @endforelse
</div>
