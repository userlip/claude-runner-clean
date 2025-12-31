<x-filament-panels::page>
    @php
        $sentryStats = $this->getSentryStats();
        $proposalStats = $this->getProposalStats();
        $ytdlpHealth = $this->getYtdlpHealth();
        $telegramStatus = $this->getTelegramStatus();
        $mcpServers = $this->getMcpServersStatus();
    @endphp

    {{-- Quick Stats Grid --}}
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
        {{-- Pending Proposals --}}
        <x-filament::section>
            <div class="flex items-center gap-4">
                <div class="flex h-12 w-12 items-center justify-center rounded-full {{ $proposalStats['pending'] > 0 ? 'bg-warning-100 dark:bg-warning-900' : 'bg-gray-100 dark:bg-gray-800' }}">
                    <x-heroicon-o-inbox-arrow-down class="h-6 w-6 {{ $proposalStats['pending'] > 0 ? 'text-warning-600 dark:text-warning-400' : 'text-gray-400' }}" />
                </div>
                <div>
                    <p class="text-2xl font-bold">{{ $proposalStats['pending'] }}</p>
                    <p class="text-sm text-gray-500">Pending Proposals</p>
                </div>
            </div>
        </x-filament::section>

        {{-- Sentry Issues --}}
        <x-filament::section>
            <div class="flex items-center gap-4">
                @if($sentryStats['available'])
                    <div class="flex h-12 w-12 items-center justify-center rounded-full {{ $sentryStats['total_issues'] > 10 ? 'bg-danger-100 dark:bg-danger-900' : 'bg-success-100 dark:bg-success-900' }}">
                        <x-heroicon-o-bug-ant class="h-6 w-6 {{ $sentryStats['total_issues'] > 10 ? 'text-danger-600 dark:text-danger-400' : 'text-success-600 dark:text-success-400' }}" />
                    </div>
                    <div>
                        <p class="text-2xl font-bold">{{ $sentryStats['total_issues'] }}</p>
                        <p class="text-sm text-gray-500">Sentry Issues</p>
                    </div>
                @else
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                        <x-heroicon-o-bug-ant class="h-6 w-6 text-gray-400" />
                    </div>
                    <div>
                        <p class="text-lg font-medium text-gray-400">Unavailable</p>
                        <p class="text-sm text-gray-500">{{ $sentryStats['error'] ?? 'Sentry' }}</p>
                    </div>
                @endif
            </div>
        </x-filament::section>

        {{-- yt-dlp Health --}}
        <x-filament::section>
            <div class="flex items-center gap-4">
                @if($ytdlpHealth['available'])
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-success-100 dark:bg-success-900">
                        <x-heroicon-o-play-circle class="h-6 w-6 text-success-600 dark:text-success-400" />
                    </div>
                    <div>
                        <p class="text-2xl font-bold">{{ number_format($ytdlpHealth['extractors']) }}</p>
                        <p class="text-sm text-gray-500">Video Extractors</p>
                    </div>
                @else
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                        <x-heroicon-o-play-circle class="h-6 w-6 text-gray-400" />
                    </div>
                    <div>
                        <p class="text-lg font-medium text-gray-400">Unavailable</p>
                        <p class="text-sm text-gray-500">yt-dlp</p>
                    </div>
                @endif
            </div>
        </x-filament::section>

        {{-- Telegram Bot --}}
        <x-filament::section>
            <div class="flex items-center gap-4">
                @if($telegramStatus['available'])
                    <div class="flex h-12 w-12 items-center justify-center rounded-full {{ $telegramStatus['last_error'] ? 'bg-warning-100 dark:bg-warning-900' : 'bg-success-100 dark:bg-success-900' }}">
                        <x-heroicon-o-chat-bubble-left-right class="h-6 w-6 {{ $telegramStatus['last_error'] ? 'text-warning-600 dark:text-warning-400' : 'text-success-600 dark:text-success-400' }}" />
                    </div>
                    <div>
                        <p class="text-lg font-bold text-success-600">Active</p>
                        <p class="text-sm text-gray-500">Telegram Bot</p>
                    </div>
                @else
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                        <x-heroicon-o-chat-bubble-left-right class="h-6 w-6 text-gray-400" />
                    </div>
                    <div>
                        <p class="text-lg font-medium text-gray-400">Not Configured</p>
                        <p class="text-sm text-gray-500">Telegram Bot</p>
                    </div>
                @endif
            </div>
        </x-filament::section>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- Recent Proposals --}}
        <x-filament::section>
            <x-slot name="heading">
                Recent Proposals
            </x-slot>
            <x-slot name="headerEnd">
                <x-filament::badge>{{ $proposalStats['total'] }} total</x-filament::badge>
            </x-slot>

            @if($proposalStats['recent']->count() > 0)
                <div class="divide-y dark:divide-gray-700">
                    @foreach($proposalStats['recent'] as $proposal)
                        <div class="flex items-center justify-between py-3">
                            <div class="flex-1">
                                <p class="font-medium">{{ $proposal->title }}</p>
                                <p class="text-sm text-gray-500">
                                    {{ $proposal->project }} &bull; {{ $proposal->created_at->diffForHumans() }}
                                </p>
                            </div>
                            <x-filament::badge :color="$proposal->status->color()">
                                {{ $proposal->status->label() }}
                            </x-filament::badge>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="py-8 text-center text-gray-500">
                    <x-heroicon-o-inbox class="mx-auto h-12 w-12 text-gray-300" />
                    <p class="mt-2">No proposals yet</p>
                </div>
            @endif
        </x-filament::section>

        {{-- MCP Servers Status --}}
        <x-filament::section>
            <x-slot name="heading">
                MCP Servers
            </x-slot>
            <x-slot name="headerEnd">
                <x-filament::badge color="success">{{ count($mcpServers) }} configured</x-filament::badge>
            </x-slot>

            <div class="divide-y dark:divide-gray-700">
                @foreach($mcpServers as $name => $server)
                    <div class="flex items-center justify-between py-3">
                        <div class="flex items-center gap-3">
                            <div class="flex h-8 w-8 items-center justify-center rounded bg-primary-100 dark:bg-primary-900">
                                <x-heroicon-o-server class="h-4 w-4 text-primary-600 dark:text-primary-400" />
                            </div>
                            <div>
                                <p class="font-medium">{{ $name }}</p>
                                <p class="text-xs text-gray-500">{{ $server['command'] }}</p>
                            </div>
                        </div>
                        <x-filament::badge color="success">Ready</x-filament::badge>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    </div>

    {{-- Detailed Sections --}}
    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- Sentry Details --}}
        @if($sentryStats['available'])
            <x-filament::section>
                <x-slot name="heading">
                    Sentry Issues by Project
                </x-slot>

                <div class="space-y-3">
                    @foreach($sentryStats['projects'] ?? [] as $project => $count)
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium capitalize">{{ $project }}</span>
                            <div class="flex items-center gap-2">
                                <div class="h-2 w-24 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                                    <div class="h-full rounded-full {{ $count > 20 ? 'bg-danger-500' : ($count > 10 ? 'bg-warning-500' : 'bg-success-500') }}"
                                         style="width: {{ min(100, $count * 2) }}%"></div>
                                </div>
                                <span class="text-sm tabular-nums">{{ $count }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        @endif

        {{-- Telegram Webhook Details --}}
        @if($telegramStatus['available'])
            <x-filament::section>
                <x-slot name="heading">
                    Telegram Webhook Status
                </x-slot>

                <dl class="space-y-3">
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Webhook URL</dt>
                        <dd class="truncate text-sm font-medium" style="max-width: 200px;">
                            {{ $telegramStatus['webhook_url'] ?: 'Not set' }}
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Pending Updates</dt>
                        <dd class="font-medium">{{ $telegramStatus['pending_updates'] }}</dd>
                    </div>
                    @if($telegramStatus['last_error'])
                        <div class="rounded-lg bg-danger-50 p-3 dark:bg-danger-900/20">
                            <p class="text-sm font-medium text-danger-700 dark:text-danger-400">Last Error</p>
                            <p class="text-sm text-danger-600 dark:text-danger-300">{{ $telegramStatus['last_error'] }}</p>
                            <p class="text-xs text-danger-500">{{ $telegramStatus['last_error_date'] }}</p>
                        </div>
                    @else
                        <div class="rounded-lg bg-success-50 p-3 dark:bg-success-900/20">
                            <p class="text-sm font-medium text-success-700 dark:text-success-400">No errors</p>
                            <p class="text-sm text-success-600 dark:text-success-300">Webhook is working correctly</p>
                        </div>
                    @endif
                </dl>
            </x-filament::section>
        @endif
    </div>

    {{-- yt-dlp Details --}}
    @if($ytdlpHealth['available'])
        <div class="mt-6">
            <x-filament::section>
                <x-slot name="heading">
                    Video Download Health (LTO2)
                </x-slot>
                <x-slot name="headerEnd">
                    <x-filament::badge color="success">v{{ $ytdlpHealth['version'] }}</x-filament::badge>
                </x-slot>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-800">
                        <p class="text-3xl font-bold text-primary-600">{{ number_format($ytdlpHealth['extractors']) }}</p>
                        <p class="text-sm text-gray-500">Supported Extractors</p>
                    </div>
                    <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-800">
                        <p class="text-3xl font-bold text-success-600">Active</p>
                        <p class="text-sm text-gray-500">System Status</p>
                    </div>
                    <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-800">
                        <p class="text-lg font-bold">{{ \Carbon\Carbon::parse($ytdlpHealth['last_checked'])->diffForHumans() }}</p>
                        <p class="text-sm text-gray-500">Last Checked</p>
                    </div>
                </div>
            </x-filament::section>
        </div>
    @endif
</x-filament-panels::page>
