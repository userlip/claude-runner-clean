<?php

namespace App\Services;

use App\Enums\ProposalStatus;
use App\Models\Proposal;
use App\Models\Task;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Api;
use Telegram\Bot\Exceptions\TelegramSDKException;
use Telegram\Bot\Keyboard\Keyboard;
use Telegram\Bot\Objects\Message;

class TelegramService
{
    private Api $telegram;

    private string $adminChatId;

    public function __construct()
    {
        $this->telegram = new Api(config('telegram.bots.claude_runner.token'));
        $this->adminChatId = config('telegram.admin_chat_id');
    }

    public function sendProposalNotification(Proposal $proposal): ?Message
    {
        try {
            $keyboard = $this->buildProposalKeyboard($proposal);

            $response = $this->telegram->sendMessage([
                'chat_id' => $this->adminChatId,
                'text' => $proposal->formatForTelegram(),
                'parse_mode' => 'Markdown',
                'reply_markup' => $keyboard,
            ]);

            // Store the message ID for later reference
            $proposal->update(['telegram_message_id' => $response->messageId]);

            return $response;
        } catch (TelegramSDKException $e) {
            Log::error('Failed to send proposal notification', [
                'proposal_id' => $proposal->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function sendErrorAlert(string $project, string $message, array $context = []): ?Message
    {
        try {
            $contextText = ! empty($context) ? "\n\n*Context:*\n```\n".json_encode($context, JSON_PRETTY_PRINT)."\n```" : '';

            $text = <<<TEXT
*Error Alert*

*Project:* `{$project}`

*Message:*
{$message}
{$contextText}
TEXT;

            return $this->telegram->sendMessage([
                'chat_id' => $this->adminChatId,
                'text' => $text,
                'parse_mode' => 'Markdown',
            ]);
        } catch (TelegramSDKException $e) {
            Log::error('Failed to send error alert', [
                'project' => $project,
                'message' => $message,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function sendCompletionNotification(Task $task): ?Message
    {
        try {
            $status = $task->status->label();
            $emoji = $task->status->value === 'completed' ? '' : '';
            $duration = $task->started_at && $task->completed_at
                ? $task->started_at->diffForHumans($task->completed_at, true)
                : 'Unknown';

            $project = $task->repository?->name ?? $task->site?->name ?? 'General Chat';

            $text = <<<TEXT
{$emoji} *Task {$status}*

*Title:* {$task->title}
*Project:* `{$project}`
*Duration:* {$duration}
*Task ID:* `{$task->uuid}`
TEXT;

            return $this->telegram->sendMessage([
                'chat_id' => $this->adminChatId,
                'text' => $text,
                'parse_mode' => 'Markdown',
            ]);
        } catch (TelegramSDKException $e) {
            Log::error('Failed to send completion notification', [
                'task_id' => $task->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function sendMessage(string $text, ?array $keyboard = null): ?Message
    {
        try {
            $params = [
                'chat_id' => $this->adminChatId,
                'text' => $text,
                'parse_mode' => 'Markdown',
            ];

            if ($keyboard) {
                $params['reply_markup'] = Keyboard::make([
                    'inline_keyboard' => $keyboard,
                ]);
            }

            return $this->telegram->sendMessage($params);
        } catch (TelegramSDKException $e) {
            Log::error('Failed to send Telegram message', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function editMessage(int $messageId, string $text, ?array $keyboard = null): ?Message
    {
        try {
            $params = [
                'chat_id' => $this->adminChatId,
                'message_id' => $messageId,
                'text' => $text,
                'parse_mode' => 'Markdown',
            ];

            if ($keyboard) {
                $params['reply_markup'] = Keyboard::make([
                    'inline_keyboard' => $keyboard,
                ]);
            }

            return $this->telegram->editMessageText($params);
        } catch (TelegramSDKException $e) {
            Log::error('Failed to edit Telegram message', [
                'message_id' => $messageId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function answerCallbackQuery(string $callbackQueryId, string $text = '', bool $showAlert = false): bool
    {
        try {
            $this->telegram->answerCallbackQuery([
                'callback_query_id' => $callbackQueryId,
                'text' => $text,
                'show_alert' => $showAlert,
            ]);

            return true;
        } catch (TelegramSDKException $e) {
            Log::error('Failed to answer callback query', [
                'callback_query_id' => $callbackQueryId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function setWebhook(string $url): bool
    {
        try {
            $this->telegram->setWebhook([
                'url' => $url,
                'allowed_updates' => ['message', 'callback_query'],
                'secret_token' => config('telegram.webhook_secret'),
            ]);

            return true;
        } catch (TelegramSDKException $e) {
            Log::error('Failed to set webhook', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function deleteWebhook(): bool
    {
        try {
            $this->telegram->deleteWebhook();

            return true;
        } catch (TelegramSDKException $e) {
            Log::error('Failed to delete webhook', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function getWebhookInfo(): array
    {
        try {
            $info = $this->telegram->getWebhookInfo();

            return $info->toArray();
        } catch (TelegramSDKException $e) {
            Log::error('Failed to get webhook info', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    public function isFromAdmin(?string $chatId): bool
    {
        return $chatId === $this->adminChatId;
    }

    public function updateProposalMessage(Proposal $proposal): ?Message
    {
        if (! $proposal->telegram_message_id) {
            return null;
        }

        $statusEmoji = match ($proposal->status) {
            ProposalStatus::Approved => '',
            ProposalStatus::Rejected => '',
            default => '',
        };

        $statusLabel = $proposal->status->label();
        $priorityEmoji = $proposal->priority->emoji();
        $priorityLabel = $proposal->priority->label();

        $text = <<<TEXT
{$statusEmoji} *Proposal {$statusLabel}*

*Title:* {$proposal->title}
*Project:* `{$proposal->project}`
*Priority:* {$priorityLabel} {$priorityEmoji}

*Description:*
{$proposal->description}
TEXT;

        if ($proposal->isRejected() && $proposal->rejection_reason) {
            $text .= "\n\n*Rejection Reason:*\n{$proposal->rejection_reason}";
        }

        if ($proposal->isApproved() && $proposal->task_id) {
            $text .= "\n\n*Task ID:* `{$proposal->task->uuid}`";
        }

        return $this->editMessage((int) $proposal->telegram_message_id, $text);
    }

    private function buildProposalKeyboard(Proposal $proposal): Keyboard
    {
        return Keyboard::make([
            'inline_keyboard' => [
                [
                    ['text' => 'Approve', 'callback_data' => "approve:{$proposal->id}"],
                    ['text' => 'Reject', 'callback_data' => "reject:{$proposal->id}"],
                ],
                [
                    ['text' => 'View Details', 'callback_data' => "details:{$proposal->id}"],
                ],
            ],
        ]);
    }
}
