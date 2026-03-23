<?php

namespace App\Livewire\Tasks;

use App\Livewire\Settings\Index as SettingsIndex;
use App\Models\Task;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.chat')]
class Show extends Component
{
    public Task $task;

    public ?string $activePanel = null;

    public function mount(string $uuid): void
    {
        $this->task = Task::where('uuid', $uuid)->firstOrFail();

        $user = auth()->user();
        $ownedViaRepository = $this->task->repository && $this->task->repository->user_id === $user->id;
        $ownedDirectly = $this->task->user_id === $user->id;

        if (! $ownedViaRepository && ! $ownedDirectly) {
            abort(403);
        }

        $configuredPanels = $user->setting('sidebar_panels', ['session-info']);

        if (is_string($configuredPanels) && array_key_exists($configuredPanels, SettingsIndex::AVAILABLE_PANELS)) {
            $this->activePanel = $configuredPanels;

            return;
        }

        $configuredPanels = is_array($configuredPanels) ? $configuredPanels : [];

        $this->activePanel = collect($configuredPanels)
            ->first(fn (mixed $panel): bool => is_string($panel) && array_key_exists($panel, SettingsIndex::AVAILABLE_PANELS))
            ?? 'session-info';
    }

    public function setActivePanel(string $panel): void
    {
        if (! array_key_exists($panel, SettingsIndex::AVAILABLE_PANELS)) {
            return;
        }

        $this->activePanel = $this->activePanel === $panel ? null : $panel;
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.tasks.show');
    }
}
