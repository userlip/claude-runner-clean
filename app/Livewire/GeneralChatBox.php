<?php

namespace App\Livewire;

use App\Enums\MessageRole;
use App\Jobs\RunGeneralChatMessageJob;
use App\Models\AiProvider;
use App\Models\GeneralChat;
use App\Models\GeneralChatMessage;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Http;
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
     * @return EloquentCollection<int, GeneralChatMessage>
     */
    #[Computed]
    public function chatMessages(): EloquentCollection
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

    /**
     * @return EloquentCollection<int, AiProvider>
     */
    #[Computed]
    public function availableProviders(): EloquentCollection
    {
        return AiProvider::where('is_active', true)->get();
    }

    #[Computed]
    public function currentProvider(): ?AiProvider
    {
        return $this->chat->aiProvider;
    }

    public function setProvider(int $providerId): void
    {
        $provider = AiProvider::where('is_active', true)->find($providerId);

        if ($provider) {
            $this->chat->update(['ai_provider_id' => $provider->id]);
            $this->chat->refresh();
        }
    }

    public function sendMessage(): void
    {
        $this->validate([
            'prompt' => 'required|string|min:1|max:100000',
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

    public function generateTitle(): void
    {
        $messages = $this->chat->messages()->oldest()->take(20)->get();

        if ($messages->isEmpty()) {
            return;
        }

        $provider = $this->chat->aiProvider ?? AiProvider::getDefault();

        if (! $provider) {
            return;
        }

        $conversationSummary = $messages->map(function ($message) {
            $role = $message->role === MessageRole::User ? 'User' : 'Assistant';

            return "{$role}: ".substr($message->content ?? '', 0, 500);
        })->join("\n\n");

        $baseUrl = $provider->base_url ?: 'https://api.anthropic.com';
        $apiKey = $provider->api_key ?: config('services.anthropic.api_key');
        $model = $provider->model ?: 'claude-sonnet-4-20250514';

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->post("{$baseUrl}/v1/messages", [
            'model' => $model,
            'max_tokens' => 50,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => "Based on this conversation, generate a short title (max 6 words, no quotes). Just respond with the title, nothing else.\n\n{$conversationSummary}",
                ],
            ],
        ]);

        if ($response->successful()) {
            $data = $response->json();
            $title = $data['content'][0]['text'] ?? null;

            if ($title) {
                $title = trim($title, " \n\r\t\v\0\"'");
                $this->chat->update(['title' => $title]);
                $this->chat->refresh();
            }
        }
    }

    public function render()
    {
        return view('livewire.general-chat-box');
    }
}
