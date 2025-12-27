<div wire:poll.3s class="recent-chats-sidebar">
    <div class="recent-chats-header">
        <span>Recent Chats</span>
    </div>
    <ul class="recent-chats-list">
        @forelse($this->recentTasks as $task)
            <li>
                <a href="{{ $this->getTaskUrl($task) }}" class="recent-chat-item" wire:navigate>
                    <span class="recent-chat-status recent-chat-status-{{ $task->status->value }}"></span>
                    <span class="recent-chat-title">{{ Str::limit($task->title ?? 'Untitled', 30) }}</span>
                    @if($task->repository)
                        <span class="recent-chat-badge">{{ Str::limit($task->repository->name, 12) }}</span>
                    @endif
                </a>
            </li>
        @empty
            <li class="recent-chats-empty">No recent chats</li>
        @endforelse
    </ul>
</div>
