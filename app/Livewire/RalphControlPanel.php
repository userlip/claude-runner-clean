<?php

namespace App\Livewire;

use App\Models\Task;
use App\Services\RalphWorkspaceService;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Process;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class RalphControlPanel extends Component implements HasForms
{
    use InteractsWithForms;

    #[Reactive]
    public Task $task;

    public bool $ralphEnabled = false;

    public ?int $ralphMaxIterations = 25;

    public float $ralphRotationThreshold = 0.7;

    public array $ralphModelRotation = [];

    public ?string $ralphBranchName = null;

    public string $verificationCommand = 'php artisan test';

    public array $userStories = [];

    public ?int $importIssueNumber = null;

    public function mount(): void
    {
        $this->ralphEnabled = $this->task->ralph_enabled ?? false;
        $this->ralphMaxIterations = $this->task->ralph_max_iterations ?? 25;
        $this->ralphRotationThreshold = $this->task->ralph_rotation_threshold ?? 0.7;
        $this->ralphModelRotation = $this->task->ralph_model_rotation ?? [];
        $this->ralphBranchName = $this->task->ralph_branch_name;
    }

    /**
     * Unused form schema - kept for potential future use with Filament form integration
     *
     * @private
     */
    private function form(Form $form): Form
    {
        return $form
            ->schema([
                Toggle::make('ralphEnabled')
                    ->label('Enable Ralph Mode')
                    ->live(),
                TextInput::make('ralphMaxIterations')
                    ->label('Max Iterations')
                    ->numeric()
                    ->default(25)
                    ->required(),
            ])
            ->statePath('data');
    }

    public function enableRalph(): void
    {
        $this->task->update([
            'ralph_enabled' => true,
            'ralph_max_iterations' => $this->ralphMaxIterations,
            'ralph_rotation_threshold' => $this->ralphRotationThreshold,
            'ralph_model_rotation' => $this->ralphModelRotation,
            'ralph_branch_name' => $this->ralphBranchName ?? "ralph/{$this->task->uuid}",
        ]);

        // Initialize workspace
        app(RalphWorkspaceService::class)->initialize($this->task, [
            'branch_name' => $this->ralphBranchName ?? "ralph/{$this->task->uuid}",
            'verification_command' => $this->verificationCommand,
            'stories' => $this->userStories,
        ]);

        $this->ralphEnabled = true;

        Notification::make()
            ->title('Ralph mode enabled')
            ->success()
            ->send();
    }

    public function disableRalph(): void
    {
        $this->task->update(['ralph_enabled' => false]);
        $this->ralphEnabled = false;

        Notification::make()
            ->title('Ralph mode disabled')
            ->info()
            ->send();
    }

    public function startRalph(): void
    {
        \App\Jobs\RunRalphJob::dispatch($this->task);

        Notification::make()
            ->title('Ralph loop started')
            ->success()
            ->send();
    }

    public function pauseRalph(): void
    {
        // Cancel pending jobs
        Bus::dispatchSync(
            new \Illuminate\Bus\PendingDispatch(function () {
                // @todo Implementation for pausing Ralph loop
            })
        );

        Notification::make()
            ->title('Ralph loop paused')
            ->info()
            ->send();
    }

    /**
     * Import GitHub Issues from a parent PRD issue into prd.json format.
     * Fetches child issues labeled 'prd-slice' that reference the parent PRD.
     */
    public function importFromGitHub(): void
    {
        if (! $this->importIssueNumber) {
            Notification::make()
                ->title('Please enter a PRD issue number')
                ->warning()
                ->send();

            return;
        }

        if (! $this->task->repository) {
            Notification::make()
                ->title('Task must be linked to a repository')
                ->danger()
                ->send();

            return;
        }

        $repo = $this->task->repository;
        $repoFullName = $repo->full_name;

        // Fetch the parent PRD issue
        $prdResult = Process::run(
            "gh issue view {$this->importIssueNumber} --repo {$repoFullName} --json title,body"
        );

        if (! $prdResult->successful()) {
            Notification::make()
                ->title('Failed to fetch PRD issue')
                ->body($prdResult->errorOutput())
                ->danger()
                ->send();

            return;
        }

        $prdData = json_decode($prdResult->output(), true);

        // Fetch child issues with prd-slice label that mention the parent
        $issuesResult = Process::run(
            "gh issue list --repo {$repoFullName} --label prd-slice --json number,title,body --limit 100"
        );

        if (! $issuesResult->successful()) {
            Notification::make()
                ->title('Failed to fetch issues')
                ->body($issuesResult->errorOutput())
                ->danger()
                ->send();

            return;
        }

        $issues = json_decode($issuesResult->output(), true) ?? [];

        // Filter to only issues that reference the parent PRD
        $parentRef = "#{$this->importIssueNumber}";
        $childIssues = collect($issues)->filter(function ($issue) use ($parentRef) {
            return str_contains($issue['body'] ?? '', $parentRef);
        })->values();

        if ($childIssues->isEmpty()) {
            Notification::make()
                ->title('No child issues found')
                ->body("No issues with label 'prd-slice' reference #{$this->importIssueNumber}")
                ->warning()
                ->send();

            return;
        }

        // Build user stories from issues
        $userStories = $childIssues->map(function ($issue, $index) {
            // Extract acceptance criteria from body
            $criteria = [];
            if (preg_match_all('/- \[ \] (.+)/m', $issue['body'] ?? '', $matches)) {
                $criteria = $matches[1];
            }

            // Extract blocked-by issue numbers
            $blockedBy = [];
            if (preg_match_all('/#(\d+)/', $this->extractSection($issue['body'] ?? '', 'Blocked by'), $matches)) {
                $blockedBy = $matches[1];
            }

            return [
                'id' => 'STORY-'.($index + 1),
                'title' => $issue['title'],
                'githubIssue' => $issue['number'],
                'priority' => $index + 1,
                'passes' => false,
                'acceptanceCriteria' => $criteria,
                'blockedBy' => $blockedBy,
            ];
        })->toArray();

        // Build prd.json
        $branchName = $this->ralphBranchName ?: "ralph/{$this->task->uuid}";
        $prd = [
            'branchName' => $branchName,
            'verificationCommand' => $this->verificationCommand,
            'parentIssue' => $this->importIssueNumber,
            'userStories' => $userStories,
        ];

        // Initialize workspace with the imported stories
        $ralph = app(RalphWorkspaceService::class);
        $ralph->initialize($this->task, [
            'branch_name' => $branchName,
            'verification_command' => $this->verificationCommand,
            'stories' => $userStories,
        ]);

        // Overwrite with the full prd including GitHub metadata
        $ralph->updatePrd($this->task, $prd);

        $this->task->update([
            'ralph_enabled' => true,
            'ralph_max_iterations' => $this->ralphMaxIterations,
            'ralph_branch_name' => $branchName,
        ]);

        $this->ralphEnabled = true;
        $this->userStories = $userStories;

        Notification::make()
            ->title("Imported {$childIssues->count()} issues from PRD #{$this->importIssueNumber}")
            ->success()
            ->send();
    }

    /**
     * Extract a section from a GitHub issue body by heading.
     */
    protected function extractSection(string $body, string $heading): string
    {
        $pattern = '/## '.preg_quote($heading, '/').'\s*\n(.*?)(?=\n## |\z)/s';
        if (preg_match($pattern, $body, $matches)) {
            return trim($matches[1]);
        }

        return '';
    }

    #[Computed]
    public function ralphStatus(): array
    {
        if (! $this->ralphEnabled) {
            return ['status' => 'disabled'];
        }

        // Read activity log
        try {
            $ralph = app(RalphWorkspaceService::class);
            $state = $ralph->readState($this->task);

            $passedStories = collect($state->prd['userStories'] ?? [])
                ->filter(fn ($s) => $s['passes'] ?? false)
                ->count();

            $totalStories = count($state->prd['userStories'] ?? []);

            return [
                'status' => $this->task->status->value,
                'iteration' => $this->task->ralph_iteration,
                'max_iterations' => $this->task->ralph_max_iterations,
                'stories_passed' => $passedStories,
                'stories_total' => $totalStories,
                'tokens_used' => $this->task->messages()->sum('tokens_in'),
                'gutter_count' => $this->task->ralph_gutter_count,
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'error' => $e->getMessage()];
        }
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.ralph-control-panel');
    }
}
