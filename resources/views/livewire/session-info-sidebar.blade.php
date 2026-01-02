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
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1rem; height: 1rem; transition: transform 0.2s; {{ $expandedSection === 'model_usage' ? 'transform: rotate(90deg);' : '' }}">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                </svg>
                <span>Model Usage</span>
            </div>
            <span class="session-info-badge">{{ count($this->modelUsage) }}</span>
        </button>
        @if($expandedSection === 'model_usage')
        <div class="session-info-content">
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
        @endif

        {{-- MCP Servers --}}
        @if(count($this->mcpServers) > 0)
        <button
            wire:click="toggleSection('mcp_servers')"
            class="session-info-header"
        >
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1rem; height: 1rem; transition: transform 0.2s; {{ $expandedSection === 'mcp_servers' ? 'transform: rotate(90deg);' : '' }}">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                </svg>
                <span>MCP Servers</span>
            </div>
            <span class="session-info-badge">{{ count($this->mcpServers) }}</span>
        </button>
        @if($expandedSection === 'mcp_servers')
        <div class="session-info-content">
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
        @endif

        {{-- Skills --}}
        @if(count($this->skills) > 0)
        <button
            wire:click="toggleSection('skills')"
            class="session-info-header"
        >
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1rem; height: 1rem; transition: transform 0.2s; {{ $expandedSection === 'skills' ? 'transform: rotate(90deg);' : '' }}">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                </svg>
                <span>Skills</span>
            </div>
            <span class="session-info-badge">{{ count($this->skills) }}</span>
        </button>
        @if($expandedSection === 'skills')
        <div class="session-info-content" style="max-height: 300px; overflow-y: auto;">
            @foreach($this->skills as $skill)
                <div class="session-info-item">
                    <span class="session-info-item-name">{{ $skill }}</span>
                </div>
            @endforeach
        </div>
        @endif
        @endif

        {{-- Tools --}}
        @if(count($this->tools) > 0)
        <button
            wire:click="toggleSection('tools')"
            class="session-info-header"
        >
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1rem; height: 1rem; transition: transform 0.2s; {{ $expandedSection === 'tools' ? 'transform: rotate(90deg);' : '' }}">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                </svg>
                <span>Tools</span>
            </div>
            <span class="session-info-badge">{{ count($this->tools) }}</span>
        </button>
        @if($expandedSection === 'tools')
        <div class="session-info-content" style="max-height: 300px; overflow-y: auto;">
            @foreach($this->tools as $tool)
                <div class="session-info-item">
                    <span class="session-info-item-name" style="font-size: 0.75rem;">{{ $tool }}</span>
                </div>
            @endforeach
        </div>
        @endif
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
