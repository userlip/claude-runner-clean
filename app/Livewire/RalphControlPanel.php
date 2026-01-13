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
