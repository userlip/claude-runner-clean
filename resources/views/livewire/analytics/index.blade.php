<div>
    <div class="flex items-center justify-between mb-6">
        <x-header title="Analytics" separator class="mb-0" />
        <select wire:model.live="period" class="select select-sm select-bordered w-40">
            <option value="all">All Time</option>
            <option value="7 days">Last 7 Days</option>
            <option value="30 days">Last 30 Days</option>
            <option value="90 days">Last 90 Days</option>
        </select>
    </div>

    @php $stats = $this->stats; @endphp

    {{-- Overview Stats --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
        <div class="stat bg-base-100 rounded-box shadow p-4">
            <div class="stat-title text-xs">Total Tasks</div>
            <div class="stat-value text-2xl">{{ number_format($stats['total_tasks']) }}</div>
        </div>
        <div class="stat bg-base-100 rounded-box shadow p-4">
            <div class="stat-title text-xs">Completed</div>
            <div class="stat-value text-2xl text-success">{{ number_format($stats['completed_tasks']) }}</div>
        </div>
        <div class="stat bg-base-100 rounded-box shadow p-4">
            <div class="stat-title text-xs">Failed</div>
            <div class="stat-value text-2xl text-error">{{ number_format($stats['failed_tasks']) }}</div>
        </div>
        <div class="stat bg-base-100 rounded-box shadow p-4">
            <div class="stat-title text-xs">Deleted</div>
            <div class="stat-value text-2xl text-base-content/50">{{ number_format($stats['deleted_tasks']) }}</div>
        </div>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
        <div class="stat bg-base-100 rounded-box shadow p-4">
            <div class="stat-title text-xs">Agent Time</div>
            <div class="stat-value text-2xl">{{ $this->formatDuration($stats['total_agent_seconds']) }}</div>
        </div>
        <div class="stat bg-base-100 rounded-box shadow p-4">
            <div class="stat-title text-xs">Messages</div>
            <div class="stat-value text-2xl">{{ number_format($stats['total_messages']) }}</div>
        </div>
        <div class="stat bg-base-100 rounded-box shadow p-4">
            <div class="stat-title text-xs">Tokens Used</div>
            <div class="stat-value text-2xl">{{ $this->formatTokens($stats['total_tokens_in'] + $stats['total_tokens_out']) }}</div>
            <div class="stat-desc text-xs">{{ $this->formatTokens($stats['total_tokens_in']) }} in / {{ $this->formatTokens($stats['total_tokens_out']) }} out</div>
        </div>
        <div class="stat bg-base-100 rounded-box shadow p-4">
            <div class="stat-title text-xs">Total Cost</div>
            <div class="stat-value text-2xl">${{ number_format($stats['total_cost'], 2) }}</div>
        </div>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
        <div class="stat bg-base-100 rounded-box shadow p-4">
            <div class="stat-title text-xs">Compactions</div>
            <div class="stat-value text-2xl">{{ number_format($stats['compactions']) }}</div>
        </div>
        <div class="stat bg-base-100 rounded-box shadow p-4">
            <div class="stat-title text-xs">Ralph Tasks</div>
            <div class="stat-value text-2xl">{{ number_format($stats['ralph_tasks']) }}</div>
        </div>
        <div class="stat bg-base-100 rounded-box shadow p-4">
            <div class="stat-title text-xs">Ralph Iterations</div>
            <div class="stat-value text-2xl">{{ number_format($stats['ralph_iterations']) }}</div>
        </div>
        <div class="stat bg-base-100 rounded-box shadow p-4">
            <div class="stat-title text-xs">Avg Task Duration</div>
            <div class="stat-value text-2xl">{{ $stats['completed_tasks'] > 0 ? $this->formatDuration((int) ($stats['total_agent_seconds'] / $stats['completed_tasks'])) : '-' }}</div>
        </div>
    </div>

    {{-- Agent Hours by Provider (prominent) --}}
    <x-card shadow class="mb-6">
        <x-header title="Agent Hours by Provider" subtitle="Daily agent running time, stacked by provider" size="text-lg" separator class="mb-4" />
        <div class="h-72">
            <x-chart wire:model="agentHoursChart" />
        </div>
    </x-card>

    {{-- Per-provider time stats --}}
    @if(count($this->providerStats) > 0)
        <div class="grid grid-cols-2 sm:grid-cols-{{ min(count($this->providerStats), 4) }} gap-3 mb-6">
            @foreach($this->providerStats as $provider)
                <div class="stat bg-base-100 rounded-box shadow p-4">
                    <div class="stat-title text-xs">{{ $provider['name'] }}</div>
                    <div class="stat-value text-2xl">{{ $this->formatDuration($provider['seconds']) }}</div>
                    <div class="stat-desc text-xs">{{ number_format($provider['tasks']) }} tasks / ${{ number_format($provider['cost'], 2) }}</div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Charts Row --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        {{-- Daily Activity --}}
        <x-card shadow class="lg:col-span-2">
            <x-header title="Daily Task Activity" size="text-lg" separator class="mb-4" />
            <div class="h-64">
                <x-chart wire:model="dailyActivityChart" />
            </div>
        </x-card>

        {{-- Provider Distribution --}}
        <x-card shadow>
            <x-header title="Tasks by Provider" size="text-lg" separator class="mb-4" />
            <div class="h-64">
                <x-chart wire:model="providerDistributionChart" />
            </div>
        </x-card>
    </div>

    {{-- Cost Over Time --}}
    <x-card shadow class="mb-6">
        <x-header title="Cost Over Time" size="text-lg" separator class="mb-4" />
        <div class="h-48">
            <x-chart wire:model="costOverTimeChart" />
        </div>
    </x-card>

    {{-- Provider Breakdown Table --}}
    <x-card shadow class="mb-6">
        <x-header title="By Provider" size="text-lg" separator class="mb-4" />
        <div class="overflow-x-auto">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Provider</th>
                        <th class="text-right">Tasks</th>
                        <th class="text-right">Time</th>
                        <th class="text-right">Tokens In</th>
                        <th class="text-right">Tokens Out</th>
                        <th class="text-right">Cost</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->providerStats as $provider)
                        <tr>
                            <td class="font-medium">{{ $provider['name'] }}</td>
                            <td class="text-right">{{ number_format($provider['tasks']) }}</td>
                            <td class="text-right">{{ $this->formatDuration($provider['seconds']) }}</td>
                            <td class="text-right">{{ $this->formatTokens($provider['tokens_in']) }}</td>
                            <td class="text-right">{{ $this->formatTokens($provider['tokens_out']) }}</td>
                            <td class="text-right">${{ number_format($provider['cost'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-base-content/40">No data</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Top Repositories --}}
        <x-card shadow>
            <x-header title="Most Worked On Repositories" size="text-lg" separator class="mb-4" />
            <div class="overflow-x-auto">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Repository</th>
                            <th class="text-right">Tasks</th>
                            <th class="text-right">Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($this->topRepositories as $repo)
                            <tr>
                                <td class="font-medium">{{ $repo['name'] }}</td>
                                <td class="text-right">{{ number_format($repo['tasks']) }}</td>
                                <td class="text-right">{{ $this->formatDuration($repo['seconds']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-base-content/40">No data</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

        {{-- Top Tools --}}
        <x-card shadow>
            <x-header title="Top Tool Usages" size="text-lg" separator class="mb-4" />
            <div class="overflow-x-auto">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Tool</th>
                            <th class="text-right">Uses</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($this->topTools as $tool => $count)
                            <tr>
                                <td class="font-mono text-sm">{{ $tool }}</td>
                                <td class="text-right">{{ number_format($count) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="text-center text-base-content/40">No data</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>
</div>
