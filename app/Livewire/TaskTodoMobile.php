<?php

namespace App\Livewire;

use App\Models\Task;
use Livewire\Attributes\Computed;
use Livewire\Component;

class TaskTodoMobile extends Component
{
    public Task $task;

    public function mount(Task $task): void
    {
        $this->task = $task;
    }

    /**
     * Get todos from task metadata.
     *
     * @return array<int, array{id: string, content: string, completed: bool}>
     */
    #[Computed]
    public function todos(): array
    {
        return $this->task->todos ?? [];
    }

    /**
     * Check if there are any todos.
     */
    #[Computed]
    public function hasTodos(): bool
    {
        return ! empty($this->todos());
    }

    /**
     * Add a new todo.
     */
    public function addTodo(): void
    {
        // This won't be called from here directly - handled by Alpine
    }

    /**
     * Toggle a todo's completion status.
     */
    public function toggleTodo(string $id): void
    {
        $todos = $this->todos();

        foreach ($todos as &$todo) {
            if ($todo['id'] === $id) {
                $todo['completed'] = ! $todo['completed'];
                break;
            }
        }
        unset($todo);

        $this->task->update(['todos' => $todos]);
        $this->dispatch('todo-updated');
    }

    public function render()
    {
        return view('livewire.task-todo-mobile');
    }
}
