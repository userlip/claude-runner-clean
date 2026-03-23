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

        @if($this->record->user?->asanaConnection)
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                        Asana Configuration
                    </h3>
                    <x-filament::badge
                        :color="$this->record->asana_project_id ? 'success' : 'gray'"
                        :icon="$this->record->asana_project_id ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle'"
                    >
                        {{ $this->record->asana_project_id ? 'Configured' : 'Not Configured' }}
                    </x-filament::badge>
                </div>

                @if($this->record->asana_project_id)
                    <div class="space-y-2 text-sm">
                        <div class="flex items-center gap-2">
                            <span class="text-gray-500 dark:text-gray-400">Project ID:</span>
                            <code class="rounded bg-gray-100 px-2 py-0.5 text-xs font-mono dark:bg-gray-800">{{ $this->record->asana_project_id }}</code>
                        </div>
                        @if($this->record->asana_testing_section_id)
                            <div class="flex items-center gap-2">
                                <span class="text-gray-500 dark:text-gray-400">Testing Section ID:</span>
                                <code class="rounded bg-gray-100 px-2 py-0.5 text-xs font-mono dark:bg-gray-800">{{ $this->record->asana_testing_section_id }}</code>
                            </div>
                        @else
                            <div class="flex items-center gap-2">
                                <span class="text-gray-500 dark:text-gray-400">Testing Section:</span>
                                <span class="text-amber-600 dark:text-amber-400">Auto-detect (test/testing/qa)</span>
                            </div>
                        @endif
                    </div>
                @else
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        No Asana project configured. Click "Configure Asana" to set up task synchronization.
                    </p>
                @endif
            </div>
        @endif

        <div>
            <h3 class="text-base font-semibold text-gray-950 dark:text-white mb-4">
                Environment Configurations
            </h3>
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>
