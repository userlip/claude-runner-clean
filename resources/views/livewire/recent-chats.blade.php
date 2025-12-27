<div wire:poll.3s class="recent-chats-sidebar">
    <div class="recent-chats-header">
        <span>Recent Chats</span>
    </div>
    <ul class="recent-chats-list">
        @forelse($this->recentChats as $chat)
            <li>
                <a href="{{ $this->getChatUrl($chat) }}" class="recent-chat-item" wire:navigate>
                    @if($chat['type'] === 'task')
                        <span class="recent-chat-status recent-chat-status-{{ $chat['model']->status->value }}"></span>
                    @else
                        <span class="recent-chat-status recent-chat-status-{{ $chat['model']->status->value }}"></span>
                    @endif
                    <span class="recent-chat-title">{{ Str::limit($this->getChatTitle($chat), 40) }}</span>
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
