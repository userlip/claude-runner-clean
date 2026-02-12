<?php

namespace App\Observers;

use App\Models\Message;
use App\Services\TelegramService;
use Illuminate\Support\Facades\Log;

class MessageObserver
{
    private TelegramService $telegram;

    public function __construct(TelegramService $telegram)
    {
        $this->telegram = $telegram;
    }

    /**
     * Handle the Message "created" event.
     * Send the message to Telegram if it's not from Telegram already.
     */
    public function created(Message $message): void
    {
        // Skip if message is from Telegram (to avoid loops)
        if ($message->from_telegram) {
            return;
        }

        // Skip if no task associated
        if (! $message->task) {
            return;
        }

        // Send to Telegram
        try {
            $telegramMessage = $this->telegram->sendTaskMessage($message);

            if ($telegramMessage) {
                // Store the Telegram message ID for reply threading
                $message->updateQuietly([
                    'telegram_message_id' => $telegramMessage->messageId,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Failed to send message to Telegram', [
                'message_id' => $message->id,
                'task_id' => $message->task_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the Message "updated" event.
     * Update the message in Telegram if content changed (for streaming updates).
     */
    public function updated(Message $message): void
    {
        // Only process assistant messages that have a Telegram message ID
        if (! $message->telegram_message_id) {
            return;
        }

        // Only update if content changed and it's an assistant message
        if (! $message->isFromAssistant()) {
            return;
        }

        // Check if content was actually updated
        if (! $message->wasChanged('content') && ! $message->wasChanged('content_blocks')) {
            return;
        }

        // Update the message in Telegram (for streaming content)
        try {
            $this->telegram->updateTaskMessage($message);
        } catch (\Throwable $e) {
            Log::error('Failed to update message in Telegram', [
                'message_id' => $message->id,
                'telegram_message_id' => $message->telegram_message_id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
