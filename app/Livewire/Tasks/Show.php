<?php

namespace App\Livewire\Tasks;

use App\Models\Task;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.chat')]
class Show extends Component
{
    public Task $task;

    /** @var array<string> */
    public array $openPanels = [];

    public function mount(string $uuid): void
    {
        $this->task = Task::where('uuid', $uuid)->firstOrFail();

        $user = auth()->user();
        $ownedViaRepository = $this->task->repository && $this->task->repository->user_id === $user->id;
        $ownedDirectly = $this->task->user_id === $user->id;

        if (! $ownedViaRepository && ! $ownedDirectly) {
            abort(403);
        }

        $this->openPanels = $user->setting('sidebar_panels', ['session-info']);
    }

    public function togglePanel(string $panel): void
    {
        if (in_array($panel, $this->openPanels)) {
            $this->openPanels = array_values(array_diff($this->openPanels, [$panel]));
        } else {
            $this->openPanels[] = $panel;
        }
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.tasks.show');
    }
}
