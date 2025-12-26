<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            AI Provider Usage
        </x-slot>

        <div class="grid gap-4 md:grid-cols-2">
            @foreach($this->getProviders() as $provider)
                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            @if($provider->name === 'claude')
                                <div class="h-8 w-8 rounded-lg bg-orange-100 flex items-center justify-center dark:bg-orange-900/20">
                                    <x-heroicon-o-sparkles class="h-4 w-4 text-orange-600 dark:text-orange-400" />
                                </div>
                            @else
                                <div class="h-8 w-8 rounded-lg bg-blue-100 flex items-center justify-center dark:bg-blue-900/20">
                                    <x-heroicon-o-bolt class="h-4 w-4 text-blue-600 dark:text-blue-400" />
                                </div>
                            @endif
                            <span class="font-medium text-gray-900 dark:text-white">{{ $provider->display_name }}</span>
                        </div>
                        <span class="text-lg font-semibold {{ $provider->getQuotaPercentage() > 80 ? 'text-red-600' : 'text-gray-900 dark:text-white' }}">
                            {{ number_format($provider->getQuotaPercentage(), 0) }}%
                        </span>
                    </div>

                    <div class="h-2 w-full rounded-full bg-gray-200 dark:bg-gray-700 mb-2">
                        <div
                            class="h-2 rounded-full {{ $provider->name === 'claude' ? 'bg-orange-500' : 'bg-blue-500' }}"
                            style="width: {{ min(100, $provider->getQuotaPercentage()) }}%"
                        ></div>
                    </div>

                    <div class="flex justify-between text-xs text-gray-500 dark:text-gray-400">
                        <span>{{ number_format($provider->quota_used) }} / {{ number_format($provider->quota_limit ?? 0) }} tokens</span>
                        @if($provider->quota_resets_at)
                            <span>Resets {{ $provider->quota_resets_at->diffForHumans() }}</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
