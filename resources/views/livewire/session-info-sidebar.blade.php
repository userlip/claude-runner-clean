<div class="file-browser" wire:poll.5s="refresh">
    <div class="file-browser-header">
        <h3 class="file-browser-title">Session Info</h3>
        @if($this->model)
            <p class="file-browser-path">{{ $this->model }}</p>
        @endif
    </div>

    <div class="file-browser-list" style="gap: 0;">
        {{-- Session Stats --}}
        @if($this->durationMs || $this->numTurns)
        <div class="session-info-section">
            <div class="session-info-stats">
                @if($this->numTurns)
                <div class="session-info-stat">
                    <span class="session-info-stat-label">Turns</span>
                    <span class="session-info-stat-value">{{ $this->numTurns }}</span>
                </div>
                @endif
                @if($this->durationMs)
                <div class="session-info-stat">
                    <span class="session-info-stat-label">Duration</span>
                    <span class="session-info-stat-value">{{ $this->formatDuration($this->durationMs) }}</span>
                </div>
                @endif
            </div>
        </div>
        @endif

        {{-- Model Usage Breakdown --}}
        @if(count($this->modelUsage) > 0)
        <button
            wire:click="toggleSection('model_usage')"
            class="session-info-header"
        >
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1rem; height: 1rem;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 3.104v5.714a2.25 2.25 0 0 1-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 0 1 4.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0 1 12 15a9.065 9.065 0 0 0-6.23-.693L5 14.5m14.8.8 1.402 1.402c1.232 1.232.65 3.318-1.067 3.611A48.309 48.309 0 0 1 12 21c-2.773 0-5.491-.235-8.135-.687-1.718-.293-2.3-2.379-1.067-3.61L5 14.5" />
                </svg>
                <span>Model Usage</span>
            </div>
            <span class="session-info-badge">{{ count($this->modelUsage) }}</span>
        </button>
        <div x-data="{ open: @js($expandedSection === 'model_usage') }"
             x-show="open || @js($expandedSection === 'model_usage')"
             x-transition
             class="session-info-content">
            @foreach($this->modelUsage as $modelName => $usage)
                <div class="session-info-item session-info-item-model">
                    <div class="session-info-item-header">
                        <span class="session-info-item-name">{{ Str::after($modelName, 'claude-') }}</span>
                        <span class="session-info-item-cost">{{ $this->formatCost($usage['costUSD'] ?? null) }}</span>
                    </div>
                    <div class="session-info-item-details">
                        <span>In: {{ number_format($usage['inputTokens'] ?? 0) }}</span>
                        <span>Out: {{ number_format($usage['outputTokens'] ?? 0) }}</span>
                        @if(($usage['cacheReadInputTokens'] ?? 0) > 0)
                            <span>Cache: {{ number_format($usage['cacheReadInputTokens']) }}</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
        @endif

        {{-- MCP Servers --}}
        @if(count($this->mcpServers) > 0)
        <button
            wire:click="toggleSection('mcp_servers')"
            class="session-info-header"
        >
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1rem; height: 1rem;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 14.25h13.5m-13.5 0a3 3 0 0 1-3-3m3 3a3 3 0 1 0 0 6h13.5a3 3 0 1 0 0-6m-16.5-3a3 3 0 0 1 3-3h13.5a3 3 0 0 1 3 3m-19.5 0a4.5 4.5 0 0 1 .9-2.7L5.737 5.1a3.375 3.375 0 0 1 2.7-1.35h7.126c1.062 0 2.062.5 2.7 1.35l2.587 3.45a4.5 4.5 0 0 1 .9 2.7m0 0a3 3 0 0 1-3 3m0 3h.008v.008h-.008v-.008Zm0-6h.008v.008h-.008v-.008Zm-3 6h.008v.008h-.008v-.008Zm0-6h.008v.008h-.008v-.008Z" />
                </svg>
                <span>MCP Servers</span>
            </div>
            <span class="session-info-badge">{{ count($this->mcpServers) }}</span>
        </button>
        <div x-data="{ open: @js($expandedSection === 'mcp_servers') }"
             x-show="open || @js($expandedSection === 'mcp_servers')"
             x-transition
             class="session-info-content">
            @foreach($this->mcpServers as $server)
                <div class="session-info-item">
                    <div class="session-info-item-header">
                        <span class="session-info-item-name">{{ $server['name'] ?? 'Unknown' }}</span>
                        <span class="session-info-status session-info-status-{{ $server['status'] ?? 'unknown' }}">
                            {{ $server['status'] ?? 'unknown' }}
                        </span>
                    </div>
                </div>
            @endforeach
        </div>
        @endif

        {{-- Skills --}}
        @if(count($this->skills) > 0)
        <button
            wire:click="toggleSection('skills')"
            class="session-info-header"
        >
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1rem; height: 1rem;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456ZM16.894 20.567 16.5 21.75l-.394-1.183a2.25 2.25 0 0 0-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 0 0 1.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 0 0 1.423 1.423l1.183.394-1.183.394a2.25 2.25 0 0 0-1.423 1.423Z" />
                </svg>
                <span>Skills</span>
            </div>
            <span class="session-info-badge">{{ count($this->skills) }}</span>
        </button>
        <div x-data="{ open: @js($expandedSection === 'skills') }"
             x-show="open || @js($expandedSection === 'skills')"
             x-transition
             class="session-info-content">
            @foreach($this->skills as $skill)
                <div class="session-info-item">
                    <span class="session-info-item-name">{{ $skill }}</span>
                </div>
            @endforeach
        </div>
        @endif

        {{-- Tools --}}
        @if(count($this->tools) > 0)
        <button
            wire:click="toggleSection('tools')"
            class="session-info-header"
        >
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1rem; height: 1rem;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437 1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008Z" />
                </svg>
                <span>Tools</span>
            </div>
            <span class="session-info-badge">{{ count($this->tools) }}</span>
        </button>
        <div x-data="{ open: @js($expandedSection === 'tools') }"
             x-show="open || @js($expandedSection === 'tools')"
             x-transition
             class="session-info-content"
             style="max-height: 300px; overflow-y: auto;">
            @foreach($this->tools as $tool)
                <div class="session-info-item">
                    <span class="session-info-item-name" style="font-size: 0.75rem;">{{ $tool }}</span>
                </div>
            @endforeach
        </div>
        @endif

        {{-- Empty state --}}
        @if(empty($this->metadata))
        <div style="padding: 1rem; text-align: center; color: rgb(107 114 128); font-size: 0.875rem;">
            <p>No session data yet</p>
            <p style="font-size: 0.75rem; margin-top: 0.5rem;">Send a message to start the session</p>
        </div>
        @endif

        {{-- Version info --}}
        @if($this->claudeCodeVersion)
        <div class="session-info-footer">
            <span>Claude Code v{{ $this->claudeCodeVersion }}</span>
        </div>
        @endif
    </div>
</div>
