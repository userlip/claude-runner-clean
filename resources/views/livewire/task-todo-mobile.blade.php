{{-- Mobile Todo Bar Component - Shown above chat input on mobile only --}}
<div
    class="task-todo-mobile-bar"
    x-data="{
        todos: @js($this->todos),
        newTodo: '',
        modalOpen: false,
        addTodo() {
            if (this.newTodo.trim()) {
                $wire.addTodo();
                this.newTodo = '';
            }
        },
        openModal() {
            this.modalOpen = true;
            this.$nextTick(() => this.$refs.mobileInput?.focus());
        },
        closeModal() {
            this.modalOpen = false;
        },
        init() {
            this.$wire.on('todo-updated', () => {
                this.todos = this.$wire.todos;
            });
        }
    }"
>
    <div class="task-todo-mobile-scroll">
        {{-- Add new todo button opens modal --}}
        <button
            type="button"
            @click="openModal()"
            class="task-todo-mobile-add"
            title="Add todo"
        >
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width: 1rem; height: 1rem;">
                <path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5Z" />
            </svg>
        </button>

        {{-- Todo items as scrollable chips --}}
        @if($this->hasTodos)
            @foreach($this->todos as $todo)
                <button
                    wire:key="mobile-todo-{{ $todo['id'] }}"
                    type="button"
                    wire:click="toggleTodo('{{ $todo['id'] }}')"
                    class="task-todo-mobile-chip {{ $todo['completed'] ? 'completed' : '' }}"
                    title="{{ $todo['content'] }}"
                >
                    @if($todo['completed'])
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="task-todo-chip-icon">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                        </svg>
                    @else
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="task-todo-chip-icon">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm-.75-11.25a.75.75 0 00-1.5 0v2.5h-2.5a.75.75 0 000 1.5h2.5v2.5a.75.75 0 001.5 0v-2.5h2.5a.75.75 0 000-1.5h-2.5v-2.5z" clip-rule="evenodd" />
                        </svg>
                    @endif
                    <span class="task-todo-chip-text">{{ $todo['content'] }}</span>
                </button>
            @endforeach
        @endif
    </div>

    {{-- Mobile Todo Input Modal --}}
    <div
        x-show="modalOpen"
        x-cloak
        class="task-todo-mobile-modal"
        @keydown.escape.window="modalOpen = false"
    >
        <div class="task-todo-mobile-modal-backdrop" @click="modalOpen = false"></div>
        <div class="task-todo-mobile-modal-content">
            <div class="task-todo-mobile-modal-header">
                <h3>Add Todo</h3>
                <button type="button" @click="modalOpen = false" class="task-todo-mobile-modal-close">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width: 1.25rem; height: 1.25rem;">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>
            </div>
            <div class="task-todo-mobile-modal-body">
                <input
                    type="text"
                    x-model="newTodo"
                    @keydown.enter="if(newTodo.trim()) { $wire.addTodo(); modalOpen = false; }"
                    placeholder="What needs to be done?"
                    class="task-todo-mobile-input"
                    x-ref="mobileInput"
                    autofocus
                >
            </div>
            <div class="task-todo-mobile-modal-footer">
                <button type="button" @click="modalOpen = false" class="task-todo-mobile-modal-cancel">Cancel</button>
                <button
                    type="button"
                    @click="if(newTodo.trim()) { $wire.addTodo(); modalOpen = false; }"
                    :disabled="!newTodo.trim()"
                    class="task-todo-mobile-modal-submit"
                >
                    Add
                </button>
            </div>
        </div>
    </div>
</div>
