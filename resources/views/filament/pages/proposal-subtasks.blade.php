<div class="space-y-2">
    @foreach ($subtasks as $index => $subtask)
        <div class="flex items-start gap-3 rounded-lg border p-3 {{ $index === $currentIndex ? 'border-primary-500 bg-primary-50 dark:bg-primary-950' : 'border-gray-200 dark:border-gray-700' }}">
            <div class="shrink-0 mt-0.5">
                @php
                    $status = $subtask['status'] ?? 'pending';
                @endphp
                @if ($status === 'completed')
                    <x-heroicon-s-check-circle class="h-5 w-5 text-success-500" />
                @elseif ($status === 'running')
                    <x-heroicon-s-arrow-path class="h-5 w-5 text-warning-500 animate-spin" />
                @elseif ($status === 'failed')
                    <x-heroicon-s-x-circle class="h-5 w-5 text-danger-500" />
                @elseif ($status === 'skipped')
                    <x-heroicon-s-minus-circle class="h-5 w-5 text-gray-400" />
                @else
                    <x-heroicon-o-clock class="h-5 w-5 text-gray-400" />
                @endif
            </div>
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                    <span class="text-sm font-medium text-gray-900 dark:text-gray-100">
                        {{ $index + 1 }}. {{ $subtask['title'] }}
                    </span>
                    <span @class([
                        'inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset',
                        'bg-gray-50 text-gray-600 ring-gray-500/10 dark:bg-gray-900 dark:text-gray-400' => $status === 'pending',
                        'bg-warning-50 text-warning-700 ring-warning-600/10 dark:bg-warning-900 dark:text-warning-400' => $status === 'running',
                        'bg-success-50 text-success-700 ring-success-600/10 dark:bg-success-900 dark:text-success-400' => $status === 'completed',
                        'bg-danger-50 text-danger-700 ring-danger-600/10 dark:bg-danger-900 dark:text-danger-400' => $status === 'failed',
                        'bg-gray-50 text-gray-500 ring-gray-400/10 dark:bg-gray-900 dark:text-gray-500' => $status === 'skipped',
                    ])>
                        {{ ucfirst($status) }}
                    </span>
                </div>
                @if (!empty($subtask['description']))
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ $subtask['description'] }}
                    </p>
                @endif
            </div>
        </div>
    @endforeach
</div>
