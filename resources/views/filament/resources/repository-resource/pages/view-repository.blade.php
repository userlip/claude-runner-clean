<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex items-center gap-4">
                <div class="flex-1">
                    <h2 class="text-lg font-semibold text-gray-950 dark:text-white">
                        {{ $this->record->full_name }}
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ $this->record->description ?: 'No description' }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    @if($this->record->private)
                        <x-filament::badge color="warning" icon="heroicon-o-lock-closed">
                            Private
                        </x-filament::badge>
                    @else
                        <x-filament::badge color="success" icon="heroicon-o-lock-open">
                            Public
                        </x-filament::badge>
                    @endif
                    <x-filament::badge color="gray">
                        {{ $this->record->default_branch }}
                    </x-filament::badge>
                </div>
            </div>
        </div>

        <div>
            <h3 class="text-base font-semibold text-gray-950 dark:text-white mb-4">
                Environment Configurations
            </h3>
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>
