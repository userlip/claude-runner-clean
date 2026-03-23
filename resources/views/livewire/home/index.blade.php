<div>
    <x-header title="{{ config('app.name') }}" separator />

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Active Tasks --}}
        @if($this->runningTasks->isNotEmpty())
            <div class="lg:col-span-3">
                <x-card shadow>
                    <x-header title="Active" size="text-lg" class="mb-2" separator />
                    <div class="flex flex-col gap-1">
                        @foreach($this->runningTasks as $task)
                            @php
                                $isRalph = $task->ralph_enabled && !$task->ralph_stopped_reason;
                                $dotColor = match(true) {
                                    $isRalph => 'bg-violet-500',
                                    $task->status === \App\Enums\TaskStatus::Running => 'bg-info',
                                    $task->status === \App\Enums\TaskStatus::WaitingForInput => 'bg-warning',
                                    default => 'bg-base-content/30',
                                };
                            @endphp
                            <a
                                href="{{ route('workbench.tasks.show', $task->uuid) }}"
                                wire:navigate
                                class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-base-200 transition-colors"
                            >
                                <span class="shrink-0 size-2.5 rounded-full {{ $dotColor }} animate-pulse"></span>
                                <div class="flex-1 min-w-0">
                                    <div class="text-sm font-medium truncate">{{ $task->title ?? 'Untitled' }}</div>
                                    @if($task->repository)
                                        <div class="text-xs text-base-content/40">{{ $task->repository->name }}</div>
                                    @endif
                                </div>
                                <span class="badge badge-xs {{ $task->status === \App\Enums\TaskStatus::WaitingForInput ? 'badge-warning' : 'badge-info' }}">
                                    {{ $task->status === \App\Enums\TaskStatus::WaitingForInput ? 'Waiting' : 'Running' }}
                                </span>
                                <span class="text-xs text-base-content/40">{{ $task->updated_at->diffForHumans() }}</span>
                            </a>
                        @endforeach
                    </div>
                </x-card>
            </div>
        @endif

        {{-- Recent Chats --}}
        <div class="lg:col-span-2">
            <x-card shadow>
                <x-header title="Recent Chats" size="text-lg" class="mb-2" separator>
                    <x-slot:actions>
                        <x-button label="New Chat" icon="o-plus" link="{{ route('workbench.tasks.create') }}" class="btn-primary btn-sm" />
                    </x-slot:actions>
                </x-header>

                <div class="flex flex-col gap-1">
                    @forelse($this->recentChats as $task)
                        @php
                            $isRalph = $task->ralph_enabled && !$task->ralph_stopped_reason
                                && !in_array($task->status, [\App\Enums\TaskStatus::Completed, \App\Enums\TaskStatus::Failed], true);
                            $dotColor = match(true) {
                                $isRalph => 'bg-violet-500',
                                $task->status === \App\Enums\TaskStatus::Running => 'bg-info',
                                $task->status === \App\Enums\TaskStatus::WaitingForInput => 'bg-warning',
                                $task->status === \App\Enums\TaskStatus::Completed => 'bg-success',
                                $task->status === \App\Enums\TaskStatus::Failed => 'bg-error',
                                default => 'bg-base-content/30',
                            };
                            $isAnimated = $isRalph || $task->status === \App\Enums\TaskStatus::Running;
                        @endphp
                        <a
                            href="{{ route('workbench.tasks.show', $task->uuid) }}"
                            wire:navigate
                            class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-base-200 transition-colors"
                        >
                            <span class="shrink-0 size-2.5 rounded-full {{ $dotColor }} {{ $isAnimated ? 'animate-pulse' : '' }}"></span>
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-medium truncate">{{ $task->title ?? 'Untitled' }}</div>
                                @if($task->repository)
                                    <div class="text-xs text-base-content/40">{{ $task->repository->name }}</div>
                                @endif
                            </div>
                            <span class="text-xs text-base-content/40">{{ $task->updated_at->diffForHumans() }}</span>
                        </a>
                    @empty
                        <div class="py-8 text-center text-base-content/40 text-sm">
                            No recent chats. Start a new one!
                        </div>
                    @endforelse
                </div>
            </x-card>
        </div>

        {{-- Quick Links --}}
        <div>
            <x-card shadow>
                <x-header title="Quick Links" size="text-lg" class="mb-2" separator />
                <div class="flex flex-col gap-1">
                    <a href="{{ route('workbench.tasks.create') }}" wire:navigate class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-base-200 transition-colors">
                        <x-icon name="o-plus-circle" class="size-5 text-primary" />
                        <span class="text-sm">New Chat</span>
                    </a>
                    <a href="{{ route('workbench.tasks.index') }}" wire:navigate class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-base-200 transition-colors">
                        <x-icon name="o-clipboard-document-list" class="size-5 text-base-content/60" />
                        <span class="text-sm">All Tasks</span>
                    </a>
                    <a href="{{ route('workbench.repositories.index') }}" wire:navigate class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-base-200 transition-colors">
                        <x-icon name="o-code-bracket" class="size-5 text-base-content/60" />
                        <span class="text-sm">Repositories</span>
                    </a>
                    <a href="{{ route('workbench.analytics.index') }}" wire:navigate class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-base-200 transition-colors">
                        <x-icon name="o-chart-bar" class="size-5 text-base-content/60" />
                        <span class="text-sm">Analytics</span>
                    </a>
                    <a href="{{ route('workbench.settings.index') }}" wire:navigate class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-base-200 transition-colors">
                        <x-icon name="o-adjustments-horizontal" class="size-5 text-base-content/60" />
                        <span class="text-sm">Settings</span>
                    </a>
                </div>
            </x-card>
        </div>
    </div>
</div>
