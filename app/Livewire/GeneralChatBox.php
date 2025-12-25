<?php

namespace App\Livewire;

use App\Enums\MessageRole;
use App\Jobs\RunGeneralChatMessageJob;
use App\Models\GeneralChat;
use App\Models\GeneralChatMessage;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

class GeneralChatBox extends Component
{
    public GeneralChat $chat;

    public string $prompt = '';

    public bool $waitingForResponse = false;

    public int $lastMessageCount = 0;

    public function mount(GeneralChat $chat): void
    {
        $this->chat = $chat;
        $this->lastMessageCount = $chat->messages()->count();
    }

    /**
     * @return Collection<int, GeneralChatMessage>
     */
    #[Computed]
    public function chatMessages(): Collection
    {
        return $this->chat->messages()->oldest()->get();
    }

    #[Computed]
    public function isRunning(): bool
    {
        return $this->chat->isRunning();
    }

    #[Computed]
    public function shouldPoll(): bool
    {
        // Check if we got a response (message count increased)
        $currentCount = $this->chat->messages()->count();
        if ($this->waitingForResponse && $currentCount > $this->lastMessageCount) {
            $this->waitingForResponse = false;
            $this->lastMessageCount = $currentCount;
        }

        return $this->isRunning || $this->waitingForResponse;
    }

    #[Computed]
    public function chatTitle(): string
    {
        return $this->chat->title ?? 'General Chat';
    }

    public function sendMessage(): void
    {
        $this->validate([
            'prompt' => 'required|string|min:1|max:10000',
        ]);

        $isFirstMessage = $this->chat->messages()->count() === 0;

        $userMessage = GeneralChatMessage::create([
            'general_chat_id' => $this->chat->id,
            'role' => MessageRole::User,
            'content' => $this->prompt,
        ]);

        RunGeneralChatMessageJob::dispatch(
            $this->chat,
            $userMessage,
            continue: ! $isFirstMessage
        );

        $this->prompt = '';
        $this->waitingForResponse = true;
        $this->lastMessageCount = $this->chat->messages()->count();
    }

    public function render()
    {
        return view('livewire.general-chat-box');
    }
}
