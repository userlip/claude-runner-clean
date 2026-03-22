<?php

namespace App\Livewire\Tasks;

use App\Models\Task;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Show extends Component
{
    public Task $task;

    public string $activePanel = 'session-info';

    public function mount(string $uuid): void
    {
        $this->task = Task::where('uuid', $uuid)->firstOrFail();
    }

    public function setActivePanel(string $panel): void
    {
        $this->activePanel = $panel;
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.tasks.show');
    }
}
