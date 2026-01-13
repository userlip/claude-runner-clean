{{-- Desktop Todo List Component - Used in sidebar bottom half --}}
<div class="task-todo-list" x-data="{
    newTodo: '',
    addTodo() {
        if (this.newTodo.trim()) {
            $wire.addTodo();
            this.newTodo = '';
        }
    }
}">
    {{-- Header --}}
    <div class="task-todo-header">
        <h3 class="task-todo-title">Todos</h3>
        @if($this->totalCount > 0)
            <span class="task-todo-count">{{ $this->completedCount }}/{{ $this->totalCount }}</span>
        @endif
    </div>

    {{-- Add todo form --}}
    <div class="task-todo-form">
        <input
            type="text"
            x-model="newTodo"
            @keydown.enter="addTodo()"
            @keydown.window="!$event.target.matches('input, textarea') && $event.key === 't' && $refs.newTodoInput.focus()"
            x-ref="newTodoInput"
            wire:model="newTodo"
            wire:keydown.enter="addTodo()"
            placeholder="Add a task..."
            class="task-todo-input"
        >
        <button
            type="button"
            @click="addTodo()"
            :disabled="!newTodo.trim()"
            class="task-todo-add-btn"
            title="Add todo (press 't' when chat is focused)"
        >
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width: 1rem; height: 1rem;">
                <path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5Z" />
            </svg>
        </button>
    </div>

    {{-- Todo list --}}
    @if($this->hasTodos)
        <div class="task-todo-items">
            @foreach($this->todos as $todo)
                <div
                    wire:key="todo-{{ $todo['id'] }}"
                    class="task-todo-item {{ $todo['completed'] ? 'completed' : '' }}"
                >
                    <button
                        type="button"
                        wire:click="toggleTodo('{{ $todo['id'] }}')"
                        class="task-todo-checkbox"
                        title="{{ $todo['completed'] ? 'Mark as incomplete' : 'Mark as complete' }}"
                    >
                        @if($todo['completed'])
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width: 1rem; height: 1rem;">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                            </svg>
                        @else
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width: 1rem; height: 1rem;">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm-.75-11.25a.75.75 0 00-1.5 0v2.5h-2.5a.75.75 0 000 1.5h2.5v2.5a.75.75 0 001.5 0v-2.5h2.5a.75.75 0 000-1.5h-2.5v-2.5z" clip-rule="evenodd" />
                            </svg>
                        @endif
                    </button>
                    <span class="task-todo-content">{{ $todo['content'] }}</span>
                    <button
                        type="button"
                        wire:click="deleteTodo('{{ $todo['id'] }}')"
                        class="task-todo-delete"
                        title="Delete todo"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width: 0.875rem; height: 0.875rem;">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>
            @endforeach
        </div>

        {{-- Actions --}}
        <div class="task-todo-actions">
            @if($this->completedCount > 0)
                <button
                    type="button"
                    wire:click="clearCompleted()"
                    class="task-todo-action-btn"
                >
                    Clear completed
                </button>
            @endif
            @if($this->totalCount > 0 && $this->completedCount < $this->totalCount)
                <button
                    type="button"
                    wire:click="toggleAll()"
                    class="task-todo-action-btn"
                >
                    {{ $this->allCompleted ? 'Uncheck all' : 'Check all' }}
                </button>
            @endif
        </div>
    @else
        <div class="task-todo-empty">
            <p>No todos yet</p>
            <p class="task-todo-empty-hint">Press 't' to add one</p>
        </div>
    @endif
</div>
