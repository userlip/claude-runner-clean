<?php

namespace App\Http\Controllers\Api;

use App\Enums\ProposalPriority;
use App\Enums\ProposalStatus;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Models\Proposal;
use App\Models\Task;
use App\Services\TelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    public function __construct(
        private TelegramService $telegram
    ) {}

    public function handle(Request $request): JsonResponse
    {
        // Validate webhook secret
        $secretToken = $request->header('X-Telegram-Bot-Api-Secret-Token');
        if ($secretToken !== config('telegram.webhook_secret')) {
            Log::warning('Invalid Telegram webhook secret token');

            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $update = $request->all();

        Log::debug('Telegram webhook received', ['update' => $update]);

        // Handle callback queries (button clicks)
        if (isset($update['callback_query'])) {
            return $this->handleCallbackQuery($update['callback_query']);
        }

        // Handle messages (commands)
        if (isset($update['message'])) {
            return $this->handleMessage($update['message']);
        }

        return response()->json(['status' => 'ok']);
    }

    private function handleCallbackQuery(array $callbackQuery): JsonResponse
    {
        $chatId = (string) ($callbackQuery['message']['chat']['id'] ?? null);
        $callbackQueryId = $callbackQuery['id'];
        $data = $callbackQuery['data'] ?? '';

        // Verify admin
        if (! $this->telegram->isFromAdmin($chatId)) {
            $this->telegram->answerCallbackQuery($callbackQueryId, 'Unauthorized', true);

            return response()->json(['status' => 'unauthorized']);
        }

        [$action, $id] = explode(':', $data) + [null, null];

        if (! $action || ! $id) {
            $this->telegram->answerCallbackQuery($callbackQueryId, 'Invalid action');

            return response()->json(['status' => 'invalid_action']);
        }

        return match ($action) {
            'approve' => $this->approveProposal((int) $id, $callbackQueryId),
            'reject' => $this->rejectProposal((int) $id, $callbackQueryId),
            'details' => $this->showProposalDetails((int) $id, $callbackQueryId),
            default => $this->handleUnknownAction($callbackQueryId),
        };
    }

    private function handleMessage(array $message): JsonResponse
    {
        $chatId = (string) ($message['chat']['id'] ?? null);
        $text = $message['text'] ?? '';

        // Verify admin
        if (! $this->telegram->isFromAdmin($chatId)) {
            return response()->json(['status' => 'unauthorized']);
        }

        // Handle commands
        if (str_starts_with($text, '/')) {
            return $this->handleCommand($text);
        }

        return response()->json(['status' => 'ok']);
    }

    private function handleCommand(string $text): JsonResponse
    {
        $parts = explode(' ', trim($text));
        $command = strtolower($parts[0]);
        $args = array_slice($parts, 1);

        return match ($command) {
            '/status' => $this->commandStatus(),
            '/pending' => $this->commandPending(),
            '/approve' => $this->commandApprove($args),
            '/reject' => $this->commandReject($args),
            '/priorities' => $this->commandPriorities(),
            '/help' => $this->commandHelp(),
            '/start' => $this->commandHelp(),
            default => $this->commandUnknown($command),
        };
    }

    private function approveProposal(int $proposalId, string $callbackQueryId): JsonResponse
    {
        $proposal = Proposal::find($proposalId);

        if (! $proposal) {
            $this->telegram->answerCallbackQuery($callbackQueryId, 'Proposal not found', true);

            return response()->json(['status' => 'not_found']);
        }

        if (! $proposal->isPending()) {
            $this->telegram->answerCallbackQuery($callbackQueryId, 'Proposal already processed', true);

            return response()->json(['status' => 'already_processed']);
        }

        // Create a task from the proposal
        $task = Task::create([
            'title' => $proposal->title,
            'user_id' => 1, // Default admin user
            'status' => TaskStatus::Pending,
        ]);

        $proposal->update([
            'status' => ProposalStatus::Approved,
            'task_id' => $task->id,
            'approved_at' => now(),
        ]);

        // Update the Telegram message
        $this->telegram->updateProposalMessage($proposal);
        $this->telegram->answerCallbackQuery($callbackQueryId, 'Proposal approved! Task created.');

        return response()->json(['status' => 'approved', 'task_id' => $task->id]);
    }

    private function rejectProposal(int $proposalId, string $callbackQueryId, ?string $reason = null): JsonResponse
    {
        $proposal = Proposal::find($proposalId);

        if (! $proposal) {
            $this->telegram->answerCallbackQuery($callbackQueryId, 'Proposal not found', true);

            return response()->json(['status' => 'not_found']);
        }

        if (! $proposal->isPending()) {
            $this->telegram->answerCallbackQuery($callbackQueryId, 'Proposal already processed', true);

            return response()->json(['status' => 'already_processed']);
        }

        $proposal->reject($reason);

        // Update the Telegram message
        $this->telegram->updateProposalMessage($proposal);
        $this->telegram->answerCallbackQuery($callbackQueryId, 'Proposal rejected.');

        return response()->json(['status' => 'rejected']);
    }

    private function showProposalDetails(int $proposalId, string $callbackQueryId): JsonResponse
    {
        $proposal = Proposal::find($proposalId);

        if (! $proposal) {
            $this->telegram->answerCallbackQuery($callbackQueryId, 'Proposal not found', true);

            return response()->json(['status' => 'not_found']);
        }

        $proposedAction = $proposal->proposed_action
            ? json_encode($proposal->proposed_action, JSON_PRETTY_PRINT)
            : 'No action details';

        $details = <<<TEXT
*Proposal Details*

*ID:* {$proposal->id}
*Title:* {$proposal->title}
*Project:* `{$proposal->project}`
*Priority:* {$proposal->priority->label()}
*Status:* {$proposal->status->label()}
*Created:* {$proposal->created_at->format('Y-m-d H:i:s')}

*Description:*
{$proposal->description}

*Proposed Action:*
```json
{$proposedAction}
```
TEXT;

        $this->telegram->sendMessage($details);
        $this->telegram->answerCallbackQuery($callbackQueryId);

        return response()->json(['status' => 'ok']);
    }

    private function handleUnknownAction(string $callbackQueryId): JsonResponse
    {
        $this->telegram->answerCallbackQuery($callbackQueryId, 'Unknown action');

        return response()->json(['status' => 'unknown_action']);
    }

    private function commandStatus(): JsonResponse
    {
        $pendingProposals = Proposal::where('status', ProposalStatus::Pending)->count();
        $runningTasks = Task::where('status', TaskStatus::Running)->count();
        $completedToday = Task::where('status', TaskStatus::Completed)
            ->whereDate('completed_at', today())
            ->count();

        $text = <<<TEXT
*System Status*

*Pending Proposals:* {$pendingProposals}
*Running Tasks:* {$runningTasks}
*Completed Today:* {$completedToday}
TEXT;

        $this->telegram->sendMessage($text);

        return response()->json(['status' => 'ok']);
    }

    private function commandPending(): JsonResponse
    {
        $proposals = Proposal::where('status', ProposalStatus::Pending)
            ->orderBy('priority', 'desc')
            ->orderBy('created_at', 'asc')
            ->take(10)
            ->get();

        if ($proposals->isEmpty()) {
            $this->telegram->sendMessage('No pending proposals.');

            return response()->json(['status' => 'ok']);
        }

        $text = "*Pending Proposals:*\n\n";

        foreach ($proposals as $proposal) {
            $emoji = $proposal->priority->emoji();
            $text .= "{$emoji} *{$proposal->id}*: {$proposal->title}\n";
            $text .= "   Project: `{$proposal->project}`\n\n";
        }

        $text .= '_Use /approve <id> or /reject <id> <reason>_';

        $this->telegram->sendMessage($text);

        return response()->json(['status' => 'ok']);
    }

    private function commandApprove(array $args): JsonResponse
    {
        if (empty($args[0])) {
            $this->telegram->sendMessage('Usage: /approve <proposal_id>');

            return response()->json(['status' => 'invalid_args']);
        }

        $proposalId = (int) $args[0];
        $proposal = Proposal::find($proposalId);

        if (! $proposal) {
            $this->telegram->sendMessage("Proposal #{$proposalId} not found.");

            return response()->json(['status' => 'not_found']);
        }

        if (! $proposal->isPending()) {
            $this->telegram->sendMessage("Proposal #{$proposalId} has already been {$proposal->status->label()}.");

            return response()->json(['status' => 'already_processed']);
        }

        // Create a task from the proposal
        $task = Task::create([
            'title' => $proposal->title,
            'user_id' => 1,
            'status' => TaskStatus::Pending,
        ]);

        $proposal->update([
            'status' => ProposalStatus::Approved,
            'task_id' => $task->id,
            'approved_at' => now(),
        ]);

        $this->telegram->updateProposalMessage($proposal);
        $this->telegram->sendMessage("Proposal #{$proposalId} approved. Task `{$task->uuid}` created.");

        return response()->json(['status' => 'approved', 'task_id' => $task->id]);
    }

    private function commandReject(array $args): JsonResponse
    {
        if (empty($args[0])) {
            $this->telegram->sendMessage('Usage: /reject <proposal_id> [reason]');

            return response()->json(['status' => 'invalid_args']);
        }

        $proposalId = (int) array_shift($args);
        $reason = ! empty($args) ? implode(' ', $args) : null;

        $proposal = Proposal::find($proposalId);

        if (! $proposal) {
            $this->telegram->sendMessage("Proposal #{$proposalId} not found.");

            return response()->json(['status' => 'not_found']);
        }

        if (! $proposal->isPending()) {
            $this->telegram->sendMessage("Proposal #{$proposalId} has already been {$proposal->status->label()}.");

            return response()->json(['status' => 'already_processed']);
        }

        $proposal->reject($reason);
        $this->telegram->updateProposalMessage($proposal);
        $this->telegram->sendMessage("Proposal #{$proposalId} rejected.");

        return response()->json(['status' => 'rejected']);
    }

    private function commandPriorities(): JsonResponse
    {
        $text = "*Proposals by Priority:*\n\n";

        foreach (ProposalPriority::cases() as $priority) {
            $count = Proposal::where('status', ProposalStatus::Pending)
                ->where('priority', $priority)
                ->count();
            $emoji = $priority->emoji();
            $text .= "{$emoji} *{$priority->label()}:* {$count}\n";
        }

        $this->telegram->sendMessage($text);

        return response()->json(['status' => 'ok']);
    }

    private function commandHelp(): JsonResponse
    {
        $text = <<<'TEXT'
*Claude Runner Bot Commands*

/status - Show system status
/pending - List pending proposals
/approve <id> - Approve a proposal
/reject <id> [reason] - Reject a proposal
/priorities - Show proposals by priority
/help - Show this help message
TEXT;

        $this->telegram->sendMessage($text);

        return response()->json(['status' => 'ok']);
    }

    private function commandUnknown(string $command): JsonResponse
    {
        $this->telegram->sendMessage("Unknown command: {$command}\nUse /help for available commands.");

        return response()->json(['status' => 'unknown_command']);
    }
}
