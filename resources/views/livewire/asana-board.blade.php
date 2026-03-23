<div class="h-full flex flex-col" wire:poll.30s>
    <x-header title="Asana Board" separator />

    @if(!$this->hasAsanaConnection())
        <x-card class="bg-base-100">
            <div class="text-center py-12">
                <x-icon name="o-link-slash" class="w-16 h-16 mx-auto text-base-content/30 mb-4" />
                <h3 class="text-lg font-semibold mb-2">No Asana Connection</h3>
                <p class="text-base-content/60 mb-4">Connect your Asana account to view and manage tasks.</p>
                <x-button label="Go to Settings" :link="route('workbench.settings.index')" icon="o-cog" />
            </div>
        </x-card>
    @else
        {{-- Project Selection & Repository Linking --}}
        <x-card class="bg-base-100 mb-4">
            <div class="flex flex-wrap gap-4 items-end">
                {{-- Workspace Selector --}}
                <div class="flex-1 min-w-[200px]">
                    <x-select
                        label="Workspace"
                        wire:model.live="selectedWorkspaceId"
                        :options="collect($workspaces)->map(fn($w) => ['label' => $w['name'], 'value' => $w['gid']])->toArray()"
                        placeholder="Select workspace"
                    />
                </div>

                {{-- Project Selector --}}
                <div class="flex-1 min-w-[200px]">
                    <x-select
                        label="Project"
                        wire:model.live="selectedProjectId"
                        :options="collect($projects)->map(fn($p) => ['label' => $p['name'], 'value' => $p['gid']])->toArray()"
                        placeholder="Select project"
                    />
                </div>

                {{-- Repository Link --}}
                <div class="flex-1 min-w-[200px]">
                    <x-select
                        label="Linked Repository"
                        wire:model.live="linkedRepositoryId"
                        :options="collect($repositories)->map(fn($r) => ['label' => $r['name'], 'value' => $r['id']])->toArray()"
                        placeholder="Link a repository..."
                    />
                </div>

                {{-- Testing Section Config --}}
                @if($linkedRepositoryId && $selectedProjectId)
                    <div class="flex-1 min-w-[200px]">
                        <x-select
                            label="Testing Section"
                            wire:model.live="testingSectionId"
                            :options="collect($sections)->map(fn($s) => ['label' => $s['name'], 'value' => $s['gid']])->toArray()"
                            placeholder="Select testing section..."
                        />
                    </div>
                @endif
            </div>

            {{-- Linked repo indicator --}}
            @if($linkedRepositoryId)
                <div class="mt-4 flex items-center gap-2 text-sm">
                    <x-icon name="o-link" class="w-4 h-4 text-success" />
                    <span class="text-base-content/70">
                        Repository linked. Tasks created will use this repository.
                    </span>
                    <button wire:click="unlinkRepository" class="text-error hover:underline ml-2">
                        Unlink
                    </button>
                </div>
            @endif
        </x-card>

        {{-- Kanban Board --}}
        @if($selectedProjectId && !empty($sections))
            <div class="flex-1 overflow-x-auto">
                <div class="flex gap-4 min-w-max pb-4">
                    @foreach($sections as $sectionId => $section)
                        <div class="w-80 flex-shrink-0">
                            {{-- Column Header --}}
                            <div class="bg-base-300/50 rounded-t-lg px-4 py-3">
                                <h3 class="font-semibold text-sm">{{ $section['name'] }}</h3>
                                <span class="text-xs text-base-content/50">
                                    {{ count($section['tasks']) }} tasks
                                </span>
                            </div>

                            {{-- Tasks --}}
                            <div class="bg-base-200/50 rounded-b-lg p-2 space-y-2 min-h-[200px]">
                                @forelse($section['tasks'] as $task)
                                    <div
                                        wire:key="task-{{ $task['gid'] }}"
                                        wire:click="openTaskPanel('{{ $task['gid'] }}')"
                                        class="bg-base-100 rounded-lg p-3 shadow-sm cursor-pointer hover:shadow-md transition-shadow"
                                    >
                                        {{-- Task Title --}}
                                        <h4 class="font-medium text-sm mb-2">{{ $task['name'] }}</h4>

                                        {{-- Task Meta --}}
                                        <div class="flex flex-wrap items-center gap-2 text-xs">
                                            {{-- Assignee --}}
                                            @if($task['assignee']['name'] ?? false)
                                                <span class="flex items-center gap-1 text-base-content/60">
                                                    <x-icon name="o-user" class="w-3 h-3" />
                                                    {{ Str::limit($task['assignee']['name'], 15) }}
                                                </span>
                                            @endif

                                            {{-- Due Date --}}
                                            @if($task['due_on'] ?? false)
                                                <span class="flex items-center gap-1 {{ $task['due_on'] < now()->format('Y-m-d') ? 'text-error' : 'text-base-content/60' }}">
                                                    <x-icon name="o-calendar" class="w-3 h-3" />
                                                    {{ $task['due_on'] }}
                                                </span>
                                            @endif

                                            {{-- Completed --}}
                                            @if($task['completed'] ?? false)
                                                <span class="text-success">
                                                    <x-icon name="o-check-circle" class="w-3 h-3" />
                                                </span>
                                            @endif
                                        </div>

                                        {{-- Tags --}}
                                        @if(!empty($task['tags']))
                                            <div class="flex flex-wrap gap-1 mt-2">
                                                @foreach(array_slice($task['tags'], 0, 3) as $tag)
                                                    <span class="px-1.5 py-0.5 bg-base-200 rounded text-xs text-base-content/60">
                                                        {{ $tag['name'] ?? 'Tag' }}
                                                    </span>
                                                @endforeach
                                                @if(count($task['tags']) > 3)
                                                    <span class="text-xs text-base-content/40">+{{ count($task['tags']) - 3 }}</span>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                @empty
                                    <div class="text-center py-8 text-sm text-base-content/40">
                                        No tasks in this section
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @elseif($selectedProjectId)
            <x-card class="bg-base-100 flex-1">
                <div class="text-center py-12">
                    <x-icon name="o-inbox" class="w-12 h-12 mx-auto text-base-content/30 mb-4" />
                    <p class="text-base-content/60">No sections found in this project.</p>
                </div>
            </x-card>
        @else
            <x-card class="bg-base-100 flex-1">
                <div class="text-center py-12">
                    <x-icon name="o-folder-open" class="w-12 h-12 mx-auto text-base-content/30 mb-4" />
                    <p class="text-base-content/60">Select a project to view tasks.</p>
                </div>
            </x-card>
        @endif
    @endif

    {{-- Task Detail Slide-over Panel --}}
    @if($showTaskPanel && $selectedTask)
        <div class="fixed inset-0 z-50 overflow-hidden">
            {{-- Backdrop --}}
            <div
                class="absolute inset-0 bg-black/50 transition-opacity"
                wire:click="closeTaskPanel"
            ></div>

            {{-- Panel --}}
            <div class="absolute inset-y-0 right-0 w-full max-w-lg bg-base-100 shadow-xl flex flex-col">
                {{-- Header --}}
                <div class="flex items-center justify-between px-6 py-4 border-b border-base-300">
                    <h2 class="text-lg font-semibold">Task Details</h2>
                    <button wire:click="closeTaskPanel" class="btn btn-ghost btn-sm btn-circle">
                        <x-icon name="o-x-mark" class="w-5 h-5" />
                    </button>
                </div>

                {{-- Content --}}
                <div class="flex-1 overflow-y-auto p-6 space-y-6">
                    {{-- Title --}}
                    <div>
                        <h3 class="text-xl font-semibold">{{ $selectedTask['name'] }}</h3>
                        @if($selectedTask['completed'] ?? false)
                            <span class="inline-flex items-center gap-1 text-success text-sm mt-1">
                                <x-icon name="o-check-circle" class="w-4 h-4" />
                                Completed
                            </span>
                        @endif
                    </div>

                    {{-- Meta Grid --}}
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        @if($selectedTask['assignee']['name'] ?? false)
                            <div>
                                <span class="text-base-content/50 block">Assignee</span>
                                <span class="flex items-center gap-1 mt-1">
                                    <x-icon name="o-user" class="w-4 h-4" />
                                    {{ $selectedTask['assignee']['name'] }}
                                </span>
                            </div>
                        @endif

                        @if($selectedTask['due_on'] ?? false)
                            <div>
                                <span class="text-base-content/50 block">Due Date</span>
                                <span class="flex items-center gap-1 mt-1">
                                    <x-icon name="o-calendar" class="w-4 h-4" />
                                    {{ $selectedTask['due_on'] }}
                                </span>
                            </div>
                        @endif

                        @if($selectedTask['section']['name'] ?? false)
                            <div>
                                <span class="text-base-content/50 block">Section</span>
                                <span class="mt-1">{{ $selectedTask['section']['name'] }}</span>
                            </div>
                        @endif

                        <div>
                            <span class="text-base-content/50 block">Created</span>
                            <span class="mt-1">{{ $selectedTask['created_at'] ?? '-' }}</span>
                        </div>
                    </div>

                    {{-- Description --}}
                    @if($selectedTask['notes'] ?? false)
                        <div>
                            <span class="text-base-content/50 text-sm block mb-2">Description</span>
                            <div class="bg-base-200 rounded-lg p-4 text-sm whitespace-pre-wrap">
                                {{ $selectedTask['notes'] }}
                            </div>
                        </div>
                    @endif

                    {{-- Tags --}}
                    @if(!empty($selectedTask['tags']))
                        <div>
                            <span class="text-base-content/50 text-sm block mb-2">Tags</span>
                            <div class="flex flex-wrap gap-2">
                                @foreach($selectedTask['tags'] as $tag)
                                    <span class="px-2 py-1 bg-base-200 rounded-full text-sm">
                                        {{ $tag['name'] ?? 'Tag' }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Asana Link --}}
                    <div>
                        <a
                            href="https://app.asana.com/0/{{ $selectedProjectId }}/{{ $selectedTask['gid'] }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="btn btn-ghost btn-sm"
                        >
                            <x-icon name="o-arrow-top-right-on-square" class="w-4 h-4" />
                            Open in Asana
                        </a>
                    </div>
                </div>

                {{-- Footer Actions --}}
                <div class="border-t border-base-300 px-6 py-4 space-y-2">
                    @if($linkedRepositoryId)
                        <x-button
                            label="Start Claude Runner Task"
                            icon="o-play"
                            wire:click="startClaudeRunnerTask"
                            class="btn-primary w-full"
                            spinner
                        />
                        <p class="text-xs text-center text-base-content/50">
                            Creates a new Claude Runner task linked to "{{ collect($repositories)->firstWhere('id', $linkedRepositoryId)['name'] ?? 'repository' }}"
                        </p>
                    @else
                        <div class="text-center text-sm text-base-content/50 py-2">
                            Link a repository to start a Claude Runner task from this Asana task.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
