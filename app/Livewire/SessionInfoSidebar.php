<?php

namespace App\Livewire;

use App\Models\Task;
use Livewire\Attributes\Computed;
use Livewire\Component;

class SessionInfoSidebar extends Component
{
    public Task $task;

    public ?string $expandedSection = null;

    public function mount(Task $task): void
    {
        $this->task = $task;
    }

    public function toggleSection(string $section): void
    {
        $this->expandedSection = $this->expandedSection === $section ? null : $section;
    }

    /**
     * Refresh task on each poll to get latest metadata.
     */
    public function refresh(): void
    {
        $this->task->refresh();
    }

    #[Computed]
    public function metadata(): array
    {
        // Refresh task to get latest metadata on each render
        $this->task->refresh();

        return $this->task->session_metadata ?? [];
    }

    #[Computed]
    public function initMetadata(): array
    {
        return $this->metadata['init'] ?? [];
    }

    #[Computed]
    public function resultMetadata(): array
    {
        return $this->metadata['result'] ?? [];
    }

    #[Computed]
    public function mcpServers(): array
    {
        return $this->initMetadata['mcp_servers'] ?? [];
    }

    #[Computed]
    public function skills(): array
    {
        return $this->initMetadata['skills'] ?? [];
    }

    #[Computed]
    public function tools(): array
    {
        return $this->initMetadata['tools'] ?? [];
    }

    #[Computed]
    public function modelUsage(): array
    {
        return $this->resultMetadata['model_usage'] ?? [];
    }

    #[Computed]
    public function model(): ?string
    {
        return $this->initMetadata['model'] ?? null;
    }

    #[Computed]
    public function claudeCodeVersion(): ?string
    {
        return $this->initMetadata['claude_code_version'] ?? null;
    }

    #[Computed]
    public function durationMs(): ?int
    {
        return $this->resultMetadata['duration_ms'] ?? null;
    }

    #[Computed]
    public function numTurns(): ?int
    {
        return $this->resultMetadata['num_turns'] ?? null;
    }

    public function formatDuration(?int $ms): string
    {
        if ($ms === null) {
            return '-';
        }

        if ($ms < 1000) {
            return $ms.'ms';
        }

        $seconds = $ms / 1000;
        if ($seconds < 60) {
            return round($seconds, 1).'s';
        }

        $minutes = floor($seconds / 60);
        $remainingSeconds = round($seconds % 60);

        return $minutes.'m '.$remainingSeconds.'s';
    }

    public function formatCost(?float $cost): string
    {
        if ($cost === null) {
            return '-';
        }

        return '$'.number_format($cost, 4);
    }

    public function render()
    {
        return view('livewire.session-info-sidebar');
    }
}
