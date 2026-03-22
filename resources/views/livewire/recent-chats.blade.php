<div>
    @if($hidden ?? false)
        {{-- Recent chats hidden by user setting --}}
    @else
    <div class="flex items-center justify-between px-4 py-1">
        <span class="text-xs font-semibold uppercase tracking-wide text-base-content/50">Recent Chats</span>
        <div class="tooltip tooltip-left" data-tip="{{ $showSystemTasks ? 'Hide system tasks' : 'Show system tasks' }}">
            <button
                wire:click="toggleSystemTasks"
                class="btn btn-ghost btn-xs {{ $showSystemTasks ? 'btn-active' : '' }}"
            >
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/>
                </svg>
            </button>
        </div>
    </div>

    <ul class="flex flex-col gap-px px-2 pb-2">
        @forelse($this->recentChats as $chat)
            <li wire:key="chat-{{ $chat['model']->id }}">
                <a
                    href="{{ $this->getChatUrl($chat) }}"
                    class="flex items-center gap-2 px-2 py-2 rounded-lg text-sm hover:bg-base-200 active:bg-base-300 transition-colors cursor-pointer {{ $this->hasUnreadReply($chat) ? 'font-semibold bg-base-200/50' : '' }}"
                >
                    @php
                        $task = $chat['model'];
                        $isRalph = $task->ralph_enabled
                            && ! $task->ralph_stopped_reason
                            && ! in_array($task->status, [\App\Enums\TaskStatus::Completed, \App\Enums\TaskStatus::Failed], true);

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
                    <span class="shrink-0 size-2.5 rounded-full {{ $dotColor }} {{ $isAnimated ? 'animate-pulse' : '' }}"></span>
                    <span class="flex flex-col flex-1 min-w-0">
                        <span class="truncate">{{ Str::limit($this->getChatTitle($chat), 24) }}</span>
                        @if($task->repository)
                            <span class="text-xs text-base-content/40 truncate">{{ $task->repository->name }}</span>
                        @endif
                    </span>
                </a>
            </li>
        @empty
            <li class="px-2 py-3 text-sm text-base-content/40 text-center">No recent chats</li>
        @endforelse
    </ul>
    @endif
</div>
