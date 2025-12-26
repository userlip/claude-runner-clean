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
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="text-orange-600 dark:text-orange-400" style="width: 1rem; height: 1rem;">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456ZM16.894 20.567 16.5 21.75l-.394-1.183a2.25 2.25 0 0 0-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 0 0 1.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 0 0 1.423 1.423l1.183.394-1.183.394a2.25 2.25 0 0 0-1.423 1.423Z" />
                                    </svg>
                                </div>
                            @else
                                <div class="h-8 w-8 rounded-lg bg-blue-100 flex items-center justify-center dark:bg-blue-900/20">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="text-blue-600 dark:text-blue-400" style="width: 1rem; height: 1rem;">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m3.75 13.5 10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z" />
                                    </svg>
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
