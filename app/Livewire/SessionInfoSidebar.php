<?php

namespace App\Livewire;

use App\Models\Task;
use Illuminate\Support\Facades\Process;
use Livewire\Attributes\Computed;
use Livewire\Component;

class SessionInfoSidebar extends Component
{
    public Task $task;

    public ?string $expandedSection = null;

    public ?string $viewingSkill = null;

    public ?string $skillContent = null;

    public function mount(Task $task): void
    {
        $this->task = $task;
    }

    public function toggleSection(string $section): void
    {
        $this->expandedSection = $this->expandedSection === $section ? null : $section;
    }

    public function viewSkill(string $skillName): void
    {
        $this->viewingSkill = $skillName;
        $this->skillContent = $this->loadSkillContent($skillName);
    }

    public function closeSkillModal(): void
    {
        $this->viewingSkill = null;
        $this->skillContent = null;
    }

    public function runSkill(string $skillName): void
    {
        $this->dispatch('insert-snippet', content: '/'.$skillName);
    }

    protected function loadSkillContent(string $skillName): string
    {
        // Skills can be in user skills directory or plugin cache
        // Files are typically SKILL.md (uppercase)
        $possiblePaths = [
            "/home/ploi/.claude/skills/{$skillName}.md",
            "/home/ploi/.claude/skills/{$skillName}/SKILL.md",
            "/home/ploi/.claude/skills/{$skillName}/skill.md",
        ];

        // Check for plugin skills (superpowers:skillname format)
        if (str_contains($skillName, ':')) {
            [$plugin, $skill] = explode(':', $skillName, 2);

            // Try glob for plugin paths - skills are in versioned directories
            $globPatterns = [
                "/home/ploi/.claude/plugins/cache/{$plugin}-marketplace/{$plugin}/*/skills/{$skill}/SKILL.md",
                "/home/ploi/.claude/plugins/cache/{$plugin}-marketplace/{$plugin}/*/skills/{$skill}.md",
                "/home/ploi/.claude/plugins/cache/*/{$plugin}/*/skills/{$skill}/SKILL.md",
                "/home/ploi/.claude/plugins/cache/*/{$plugin}/*/skills/{$skill}.md",
            ];

            foreach ($globPatterns as $pattern) {
                $matches = glob($pattern);
                if (! empty($matches)) {
                    // Use the first match (most recent version typically)
                    $possiblePaths = array_merge($matches, $possiblePaths);
                }
            }
        }

        foreach ($possiblePaths as $path) {
            if (file_exists($path) && is_readable($path)) {
                return file_get_contents($path);
            }
        }

        // Try using claude CLI to get skill content
        $result = Process::timeout(10)
            ->path($this->task->working_directory)
            ->run("claude skill show {$skillName} 2>/dev/null || echo 'Skill not found or not accessible'");

        if ($result->successful() && ! str_contains($result->output(), 'Skill not found')) {
            return $result->output();
        }

        return "Could not load skill content for: {$skillName}\n\nThe skill may be a built-in skill or located in a non-standard path.";
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

    #[Computed]
    public function shouldPoll(): bool
    {
        return $this->task->isRunning();
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
