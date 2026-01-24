<div wire:poll.5s class="recent-chats-sidebar">
    <div class="recent-chats-header">
        <span>Recent Chats</span>
        <button
            wire:click="toggleSystemTasks"
            class="recent-chats-toggle {{ $showSystemTasks ? 'active' : '' }}"
            title="{{ $showSystemTasks ? 'Hide system tasks' : 'Show system tasks' }}"
        >
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/>
            </svg>
        </button>
    </div>
    <ul class="recent-chats-list">
        @forelse($this->recentChats as $chat)
            <li wire:key="chat-{{ $chat['model']->id }}">
                <a href="{{ $this->getChatUrl($chat) }}" class="recent-chat-item">
                    <span class="recent-chat-status recent-chat-status-{{ $chat['model']->status->value }}"></span>
                    <span class="recent-chat-title">
                        @if($this->hasUnreadReply($chat))
                            <span class="recent-chat-unread">💬</span>
                        @endif
                        {{ Str::limit($this->getChatTitle($chat), 38) }}
                    </span>
                    @if($badge = $this->getChatBadge($chat))
                        <span class="recent-chat-badge">{{ Str::limit($badge, 12) }}</span>
                    @elseif($chat['type'] === 'general')
                        <span class="recent-chat-badge recent-chat-badge-general">General</span>
                    @endif
                </a>
            </li>
        @empty
            <li class="recent-chats-empty">No recent chats</li>
        @endforelse
    </ul>
</div>
