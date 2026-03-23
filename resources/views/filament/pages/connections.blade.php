<x-filament-panels::page>
    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
        @foreach ($this->getConnections() as $item)
            @php
                $type = $item['type'];
                $connection = $item['connection'];
                $isConnected = $item['is_connected'];
                $details = $item['details'];
            @endphp

            <x-filament::section>
                <div class="flex items-start gap-4">
                    {{-- Icon --}}
                    <div @class([
                        'flex h-12 w-12 shrink-0 items-center justify-center rounded-lg',
                        'bg-success-50 dark:bg-success-950' => $isConnected,
                        'bg-gray-100 dark:bg-gray-800' => ! $isConnected,
                    ])>
                        @switch($type)
                            @case(\App\Enums\ConnectionType::GitHub)
                                <x-heroicon-m-code-bracket @class([
                                    'h-6 w-6',
                                    'text-success-600 dark:text-success-400' => $isConnected,
                                    'text-gray-500 dark:text-gray-400' => ! $isConnected,
                                ]) />
                                @break
                            @case(\App\Enums\ConnectionType::GoogleAnalytics)
                                <x-heroicon-m-chart-bar @class([
                                    'h-6 w-6',
                                    'text-success-600 dark:text-success-400' => $isConnected,
                                    'text-gray-500 dark:text-gray-400' => ! $isConnected,
                                ]) />
                                @break
                            @case(\App\Enums\ConnectionType::SearchConsole)
                                <x-heroicon-m-magnifying-glass @class([
                                    'h-6 w-6',
                                    'text-success-600 dark:text-success-400' => $isConnected,
                                    'text-gray-500 dark:text-gray-400' => ! $isConnected,
                                ]) />
                                @break
                            @case(\App\Enums\ConnectionType::Asana)
                                <x-heroicon-m-check-circle @class([
                                    'h-6 w-6',
                                    'text-success-600 dark:text-success-400' => $isConnected,
                                    'text-gray-500 dark:text-gray-400' => ! $isConnected,
                                ]) />
                                @break
                        @endswitch
                    </div>

                    {{-- Content --}}
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                                {{ $type->label() }}
                            </h3>
                            @if ($isConnected)
                                <span class="inline-flex items-center rounded-full bg-success-50 px-2 py-0.5 text-xs font-medium text-success-700 dark:bg-success-900/20 dark:text-success-400">
                                    {{ $details['status_text'] }}
                                </span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                                    {{ $details['status_text'] }}
                                </span>
                            @endif
                        </div>

                        @if ($isConnected && isset($details['title']))
                            <p class="mt-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ $details['title'] }}
                            </p>
                        @endif

                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ $details['description'] }}
                        </p>

                        @if (isset($details['property_id']))
                            <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                                Property: {{ $details['property_id'] }}
                            </p>
                        @endif
                    </div>

                    {{-- Action --}}
                    <div class="shrink-0">
                        <x-filament::button
                            tag="a"
                            :href="$this->getSettingsRoute($type)"
                            :color="$isConnected ? 'gray' : 'primary'"
                            size="sm"
                        >
                            {{ $isConnected ? 'Manage' : 'Connect' }}
                        </x-filament::button>
                    </div>
                </div>
            </x-filament::section>
        @endforeach
    </div>
</x-filament-panels::page>
