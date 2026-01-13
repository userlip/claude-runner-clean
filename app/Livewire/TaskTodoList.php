<?php

namespace App\Livewire;

use App\Models\Task;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

class TaskTodoList extends Component
{
    public Task $task;

    public string $newTodo = '';

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
     * Check if all todos are completed.
     */
    #[Computed]
    public function allCompleted(): bool
    {
        $todos = $this->todos();

        return ! empty($todos) && collect($todos)->every('completed');
    }

    /**
     * Get count of completed todos.
     */
    #[Computed]
    public function completedCount(): int
    {
        return collect($this->todos())->where('completed', true)->count();
    }

    /**
     * Get total todo count.
     */
    #[Computed]
    public function totalCount(): int
    {
        return count($this->todos());
    }

    /**
     * Add a new todo.
     */
    public function addTodo(): void
    {
        $content = trim($this->newTodo);

        if (empty($content)) {
            return;
        }

        $todos = $this->todos();
        $todos[] = [
            'id' => (string) Str::uuid(),
            'content' => $content,
            'completed' => false,
        ];

        $this->task->update(['todos' => $todos]);
        $this->newTodo = '';

        $this->dispatch('todo-updated');
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

    /**
     * Delete a todo.
     */
    public function deleteTodo(string $id): void
    {
        $todos = collect($this->todos())->reject(fn ($todo) => $todo['id'] === $id)->values()->all();

        $this->task->update(['todos' => $todos]);
        $this->dispatch('todo-updated');
    }

    /**
     * Clear all completed todos.
     */
    public function clearCompleted(): void
    {
        $todos = collect($this->todos())->where('completed', false)->values()->all();

        $this->task->update(['todos' => $todos]);
        $this->dispatch('todo-updated');
    }

    /**
     * Toggle all todos.
     */
    public function toggleAll(): void
    {
        $todos = $this->todos();
        $newState = ! $this->allCompleted;

        foreach ($todos as &$todo) {
            $todo['completed'] = $newState;
        }
        unset($todo);

        $this->task->update(['todos' => $todos]);
        $this->dispatch('todo-updated');
    }

    public function render()
    {
        return view('livewire.task-todo-list');
    }
}
