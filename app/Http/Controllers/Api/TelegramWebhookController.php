<?php

namespace App\Http\Controllers\Api;

use App\Enums\MessageRole;
use App\Enums\MessageStatus;
use App\Enums\ProposalPriority;
use App\Enums\ProposalStatus;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Jobs\DeleteTaskJob;
use App\Jobs\DeployToSiteJob;
use App\Models\Message;
use App\Models\Proposal;
use App\Models\Repository;
use App\Models\Site;
use App\Models\Task;
use App\Services\TelegramMenuService;
use App\Services\TelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    private TelegramMenuService $menuService;

    public function __construct(
        private TelegramService $telegram,
        TelegramMenuService $menuService
    ) {
        $this->menuService = $menuService;
    }

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

        // Deduplication: prevent processing the same callback multiple times
        $cacheKey = "telegram:callback:{$callbackQueryId}";
        if (Cache::has($cacheKey)) {
            return response()->json(['status' => 'already_processed']);
        }
        Cache::put($cacheKey, true, now()->addMinutes(5));

        $parts = explode(':', $data);
        $action = $parts[0] ?? null;

        if (! $action) {
            $this->telegram->answerCallbackQuery($callbackQueryId, 'Invalid action');

            return response()->json(['status' => 'invalid_action']);
        }

        // Handle menu navigation callbacks
        if ($action === 'menu') {
            return $this->handleMenuCallback($parts, $callbackQueryId);
        }

        // Handle task callbacks
        if ($action === 'task') {
            return $this->handleTaskCallback($parts, $callbackQueryId);
        }

        // Handle proposal callbacks
        if ($action === 'proposal') {
            return $this->handleProposalCallback($parts, $callbackQueryId);
        }

        // Handle repo callbacks
        if ($action === 'repo') {
            return $this->handleRepoCallback($parts, $callbackQueryId);
        }

        // Handle site callbacks
        if ($action === 'site') {
            return $this->handleSiteCallback($parts, $callbackQueryId);
        }

        // Handle new task callbacks
        if ($action === 'newtask') {
            return $this->handleNewTaskCallback($parts, $callbackQueryId, $chatId);
        }

        // Handle system callbacks
        if ($action === 'system') {
            return $this->handleSystemCallback($parts, $callbackQueryId);
        }

        // Handle reply callbacks (for channel-like messaging)
        if ($action === 'reply') {
            return $this->handleReplyCallback($parts, $callbackQueryId, $chatId);
        }

        // Legacy callback format (approve:ID, reject:ID, details:ID)
        $id = $parts[1] ?? null;
        if (! $id) {
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

    private function handleMenuCallback(array $parts, string $callbackQueryId): JsonResponse
    {
        $menu = $parts[1] ?? null;

        $this->telegram->answerCallbackQuery($callbackQueryId);

        match ($menu) {
            'tasks' => $this->menuService->showTasksMenu(),
            'proposals' => $this->menuService->showProposalsMenu(),
            'repos' => $this->menuService->showRepositoriesMenu(),
            'sites' => $this->menuService->showSitesMenu(),
            'providers' => $this->menuService->showAiProvidersMenu(),
            'system' => $this->menuService->showSystemMenu(),
            'main', null => $this->menuService->showMainMenu(),
            default => $this->menuService->showMainMenu(),
        };

        return response()->json(['status' => 'ok']);
    }

    private function handleTaskCallback(array $parts, string $callbackQueryId): JsonResponse
    {
        $subAction = $parts[1] ?? null;
        $taskId = $parts[2] ?? null;

        if (! $subAction || ! $taskId) {
            $this->telegram->answerCallbackQuery($callbackQueryId, 'Invalid task action');

            return response()->json(['status' => 'invalid_action']);
        }

        $this->telegram->answerCallbackQuery($callbackQueryId);

        return match ($subAction) {
            'view' => $this->viewTask((int) $taskId),
            'cancel' => $this->cancelTask((int) $taskId),
            'messages' => $this->showTaskMessages((int) $taskId),
            default => response()->json(['status' => 'unknown_action']),
        };
    }

    private function handleProposalCallback(array $parts, string $callbackQueryId): JsonResponse
    {
        $subAction = $parts[1] ?? null;
        $proposalId = $parts[2] ?? null;

        if (! $subAction || ! $proposalId) {
            $this->telegram->answerCallbackQuery($callbackQueryId, 'Invalid proposal action');

            return response()->json(['status' => 'invalid_action']);
        }

        return match ($subAction) {
            'approve' => $this->approveProposal((int) $proposalId, $callbackQueryId),
            'reject' => $this->rejectProposal((int) $proposalId, $callbackQueryId),
            'details' => $this->showProposalDetails((int) $proposalId, $callbackQueryId),
            default => $this->handleUnknownAction($callbackQueryId),
        };
    }

    private function handleRepoCallback(array $parts, string $callbackQueryId): JsonResponse
    {
        $subAction = $parts[1] ?? null;
        $repoId = $parts[2] ?? null;

        if (! $subAction || ! $repoId) {
            $this->telegram->answerCallbackQuery($callbackQueryId, 'Invalid repo action');

            return response()->json(['status' => 'invalid_action']);
        }

        $this->telegram->answerCallbackQuery($callbackQueryId);

        if ($subAction === 'task') {
            return $this->createTaskForRepo((int) $repoId);
        }

        return response()->json(['status' => 'unknown_action']);
    }

    private function handleSiteCallback(array $parts, string $callbackQueryId): JsonResponse
    {
        $subAction = $parts[1] ?? null;
        $siteId = $parts[2] ?? null;

        if (! $subAction || ! $siteId) {
            $this->telegram->answerCallbackQuery($callbackQueryId, 'Invalid site action');

            return response()->json(['status' => 'invalid_action']);
        }

        return match ($subAction) {
            'deploy' => $this->deploySite((int) $siteId, $callbackQueryId),
            'task' => $this->createTaskForSite((int) $siteId, $callbackQueryId),
            default => $this->handleUnknownAction($callbackQueryId),
        };
    }

    private function handleNewTaskCallback(array $parts, string $callbackQueryId, string $chatId): JsonResponse
    {
        $type = $parts[1] ?? null;

        $this->telegram->answerCallbackQuery($callbackQueryId);

        if ($type === 'general') {
            Cache::put("telegram:{$chatId}:mode", 'new_task', now()->addMinutes(5));
            $this->telegram->sendMessage('Please send me the task title starting with "New Task:"');

            return response()->json(['status' => 'ok']);
        }

        if ($type === 'repo') {
            $repoId = $parts[2] ?? null;
            if ($repoId) {
                Cache::put("telegram:{$chatId}:mode", 'new_task_repo:'.$repoId, now()->addMinutes(5));
                $repo = Repository::find($repoId);
                $this->telegram->sendMessage("Creating task for *{$repo->name}*.\n\nPlease send me the task title starting with \"New Task:\"");
            }

            return response()->json(['status' => 'ok']);
        }

        return response()->json(['status' => 'unknown_action']);
    }

    private function handleSystemCallback(array $parts, string $callbackQueryId): JsonResponse
    {
        $subAction = $parts[1] ?? null;

        $this->telegram->answerCallbackQuery($callbackQueryId);

        if ($subAction === 'status') {
            return $this->menuService->showSystemStatus();
        }

        return response()->json(['status' => 'unknown_action']);
    }

    private function handleMessage(array $message): JsonResponse
    {
        $chatId = (string) ($message['chat']['id'] ?? null);
        $text = $message['text'] ?? '';
        $messageId = $message['message_id'] ?? null;

        // Verify admin
        if (! $this->telegram->isFromAdmin($chatId)) {
            return response()->json(['status' => 'unauthorized']);
        }

        // Deduplication: prevent processing the same message multiple times
        if ($messageId) {
            $cacheKey = "telegram:processed:{$messageId}";
            if (Cache::has($cacheKey)) {
                return response()->json(['status' => 'already_processed']);
            }
            Cache::put($cacheKey, true, now()->addMinutes(5));
        }

        // Handle commands
        if (str_starts_with($text, '/')) {
            return $this->handleCommand($text);
        }

        // Check if user is in reply mode (for channel-like messaging)
        $replyMode = Cache::get("telegram:{$chatId}:reply_mode");
        if ($replyMode) {
            return $this->handleReplyMessage($chatId, $text);
        }

        // Handle menu text inputs
        return $this->handleMenuText($chatId, $text);
    }

    private function handleCommand(string $text): JsonResponse
    {
        $parts = explode(' ', trim($text));
        $command = strtolower($parts[0]);
        $args = array_slice($parts, 1);

        return match ($command) {
            '/start' => $this->showMainMenu(),
            '/menu' => $this->showMainMenu(),
            '/status' => $this->menuService->showSystemStatus(),
            '/pending' => $this->menuService->showPendingProposals(),
            '/approve' => $this->commandApprove($args),
            '/reject' => $this->commandReject($args),
            '/priorities' => $this->commandPriorities(),
            '/help' => $this->commandHelp(),
            '/task' => $this->commandViewTask($args),
            default => $this->commandUnknown($command),
        };
    }

    private function handleMenuText(string $chatId, string $text): JsonResponse
    {
        // Check if user is in a specific mode
        $mode = Cache::get("telegram:{$chatId}:mode");

        if ($mode === 'new_task') {
            Cache::forget("telegram:{$chatId}:mode");

            return $this->menuService->handleNewTaskInput($text);
        }

        // Handle menu button presses
        match ($text) {
            // Main Menu
            'Tasks' => $this->menuService->showTasksMenu(),
            'Proposals' => $this->menuService->showProposalsMenu(),
            'Repositories' => $this->menuService->showRepositoriesMenu(),
            'Sites' => $this->menuService->showSitesMenu(),
            'AI Providers' => $this->menuService->showAiProvidersMenu(),
            'System' => $this->menuService->showSystemMenu(),
            'Back to Main Menu' => $this->menuService->showMainMenu(),

            // Tasks Menu
            'Running Tasks' => $this->menuService->showRunningTasks(),
            'Recent Tasks' => $this->menuService->showRecentTasks(),
            'New Task' => $this->menuService->promptNewTask(),
            'Search Task' => $this->telegram->sendMessage('To search, use: /task <uuid>'),
            'Back to Tasks' => $this->menuService->showTasksMenu(),

            // Proposals Menu
            'Pending Proposals' => $this->menuService->showPendingProposals(),
            'All Proposals' => $this->menuService->showProposalsMenu(),
            'Back to Proposals' => $this->menuService->showProposalsMenu(),

            // Repositories Menu
            'List Repositories' => $this->menuService->showRepositories(),
            'Sync All' => $this->telegram->sendMessage('Syncing all repositories...'),
            'Back to Repositories' => $this->menuService->showRepositoriesMenu(),

            // Sites Menu
            'List Sites' => $this->menuService->showSites(),
            'Deploy Site' => $this->telegram->sendMessage('To deploy, select a site from the list or use: /deploy <site_id>'),
            'Back to Sites' => $this->menuService->showSitesMenu(),

            // AI Providers Menu
            'View Providers' => $this->menuService->showAiProviders(),
            'Switch Provider' => $this->telegram->sendMessage('Provider switching coming soon!'),
            'Back to AI Providers' => $this->menuService->showAiProvidersMenu(),

            // System Menu
            'System Status' => $this->menuService->showSystemStatus(),
            'Schedules' => $this->menuService->showSchedules(),
            'Back to System' => $this->menuService->showSystemMenu(),

            default => null,
        };

        return response()->json(['status' => 'ok']);
    }

    private function showMainMenu(): JsonResponse
    {
        $this->menuService->showMainMenu();

        return response()->json(['status' => 'ok']);
    }

    private function handleNewTaskPrompt(string $chatId): JsonResponse
    {
        Cache::put("telegram:{$chatId}:mode", 'new_task', now()->addMinutes(5));
        $this->menuService->promptNewTask();

        return response()->json(['status' => 'ok']);
    }

    private function handleSyncAllRepos(): JsonResponse
    {
        // Dispatch sync job for all repos
        $repos = Repository::all();
        foreach ($repos as $repo) {
            // You could dispatch a sync job here
        }

        $this->telegram->sendMessage("Syncing {$repos->count()} repositories...");

        return response()->json(['status' => 'ok']);
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

    private function viewTask(int $taskId): JsonResponse
    {
        $task = Task::with(['repository', 'site', 'messages'])->find($taskId);

        if (! $task) {
            $this->telegram->sendMessage('Task not found.');

            return response()->json(['status' => 'not_found']);
        }

        $project = $task->repository?->name ?? $task->site?->name ?? 'General Chat';
        $duration = $task->started_at && $task->completed_at
            ? $task->started_at->diffForHumans($task->completed_at, true)
            : ($task->started_at ? $task->started_at->diffForHumans(now(), true) : 'Not started');

        $messageCount = $task->messages->count();
        $lastMessage = $task->messages->last()?->created_at?->diffForHumans() ?? 'No messages';

        // Status emoji
        $statusEmoji = match ($task->status) {
            TaskStatus::Completed => 'green',
            TaskStatus::Running => 'yellow',
            TaskStatus::Failed => 'red',
            TaskStatus::Pending => 'white',
            TaskStatus::WaitingForInput => 'pause',
            default => 'white',
        };

        $text = <<<TEXT
{$statusEmoji} *Task Details*

*UUID:* `{$task->uuid}`
*Title:* {$task->title}
*Status:* {$task->status->label()}
*Project:* `{$project}`

*Messages:* {$messageCount}
*Last Activity:* {$lastMessage}
*Duration:* {$duration}
TEXT;

        $keyboard = [];

        if ($task->isRunning()) {
            $keyboard[] = [
                ['text' => 'Cancel Task', 'callback_data' => "task:cancel:{$task->id}"],
            ];
        }

        if ($messageCount > 0) {
            $keyboard[] = [
                ['text' => 'View Messages', 'callback_data' => "task:messages:{$task->id}"],
            ];
        }

        $keyboard[] = [['text' => 'Back to Tasks', 'callback_data' => 'menu:tasks']];

        $this->telegram->sendMessage($text, $keyboard);

        return response()->json(['status' => 'ok']);
    }

    private function cancelTask(int $taskId): JsonResponse
    {
        $task = Task::find($taskId);

        if (! $task) {
            $this->telegram->sendMessage('Task not found.');

            return response()->json(['status' => 'not_found']);
        }

        if (! $task->isRunning()) {
            $this->telegram->sendMessage("Task `{$task->uuid}` is not running.");

            return response()->json(['status' => 'not_running']);
        }

        // Delete the task workspace to stop it
        DeleteTaskJob::dispatch($taskId);

        $task->markAsFailed();

        $keyboard = [
            [['text' => 'Back to Tasks', 'callback_data' => 'menu:tasks']],
        ];

        $this->telegram->sendMessage("red Task `{$task->uuid}` has been cancelled.", $keyboard);

        return response()->json(['status' => 'cancelled']);
    }

    private function showTaskMessages(int $taskId): JsonResponse
    {
        $task = Task::with(['messages' => function ($query) {
            // Get last 8 messages for a good conversation view
            $query->orderBy('created_at', 'desc')->take(8);
        }])->find($taskId);

        if (! $task) {
            $this->telegram->sendPlainMessage('Task not found.');

            return response()->json(['status' => 'not_found']);
        }

        // Reverse to show oldest first
        $messages = $task->messages->reverse();

        if ($messages->isEmpty()) {
            $keyboard = [
                [['text' => 'Back to Task', 'callback_data' => "task:view:{$taskId}"]],
            ];

            $this->telegram->sendPlainMessage('No messages in this task.', $keyboard);

            return response()->json(['status' => 'ok']);
        }

        // Send header message
        $totalMessages = $task->messages()->count();
        $headerText = "Task: {$task->uuid}\n";
        $headerText .= "{$task->title}\n";
        if ($totalMessages > 8) {
            $headerText .= "\nShowing last 8 of {$totalMessages} messages";
        }

        $this->telegram->sendPlainMessage($headerText);

        // Send each message as a separate bubble
        foreach ($messages as $message) {
            // Skip user messages - the user already knows what they wrote
            // Only show assistant and system messages
            if ($message->role !== MessageRole::User) {
                $this->sendMessageBubble($message, $task);
            }
        }

        // Send navigation footer
        $keyboard = [
            [
                ['text' => 'Reply', 'callback_data' => "reply:{$task->id}"],
                ['text' => 'Back to Task', 'callback_data' => "task:view:{$task->id}"],
            ],
        ];

        $footerText = "-------------------\n";
        $footerText .= 'Use Reply to continue the conversation';

        $this->telegram->sendPlainMessage($footerText, $keyboard);

        return response()->json(['status' => 'ok']);
    }

    /**
     * Send a single message as a native-looking Telegram message bubble.
     */
    private function sendMessageBubble(Message $message, Task $task): void
    {
        $time = $message->created_at->format('H:i');
        $content = $message->content ?? '';

        // Skip empty messages
        if (empty(trim($content))) {
            $content = '(empty message)';
        }

        // Truncate very long messages
        if (strlen($content) > 3000) {
            $content = substr($content, 0, 3000)."\n\n... (message truncated)";
        }

        // Format based on role
        if ($message->role === MessageRole::Assistant) {
            $text = "Claude · {$time}\n\n";
        } elseif ($message->role === MessageRole::System) {
            $text = "System · {$time}\n\n";
        } else {
            $text = "You · {$time}\n\n";
        }

        $text .= $content;

        // Add action buttons for this message
        $keyboard = [
            [
                ['text' => 'View Full', 'url' => $this->getTaskUrl($task)],
            ],
        ];

        // Use plain message to avoid markdown escaping issues
        $this->telegram->sendPlainMessage($text, $keyboard);
    }

    /**
     * Get the web URL for a task.
     */
    private function getTaskUrl(Task $task): string
    {
        $baseUrl = config('app.url');

        return "{$baseUrl}/admin/tasks/{$task->id}";
    }

    private function createTaskForRepo(int $repoId): JsonResponse
    {
        $repo = Repository::find($repoId);

        if (! $repo) {
            $this->telegram->sendMessage('Repository not found.');

            return response()->json(['status' => 'not_found']);
        }

        $task = Task::create([
            'title' => "Work on {$repo->name}",
            'user_id' => 1,
            'repository_id' => $repoId,
            'status' => TaskStatus::Pending,
        ]);

        $keyboard = [
            [
                ['text' => 'View Task', 'callback_data' => "task:view:{$task->id}"],
                ['text' => 'Back to Repos', 'callback_data' => 'menu:repos'],
            ],
        ];

        $this->telegram->sendMessage(
            "Task created for *{$repo->name}*!\n\n*UUID:* `{$task->uuid}`",
            $keyboard
        );

        return response()->json(['status' => 'ok', 'task_id' => $task->id]);
    }

    private function deploySite(int $siteId, string $callbackQueryId): JsonResponse
    {
        $site = Site::with('repository')->find($siteId);

        if (! $site) {
            $this->telegram->answerCallbackQuery($callbackQueryId, 'Site not found', true);

            return response()->json(['status' => 'not_found']);
        }

        DeployToSiteJob::dispatch($site);

        $this->telegram->answerCallbackQuery($callbackQueryId, 'Deployment started!');

        $keyboard = [
            [['text' => 'Back to Sites', 'callback_data' => 'menu:sites']],
        ];

        $this->telegram->sendMessage(
            "Deployment started for *{$site->name}* ({$site->domain})",
            $keyboard
        );

        return response()->json(['status' => 'ok']);
    }

    private function createTaskForSite(int $siteId, string $callbackQueryId): JsonResponse
    {
        $site = Site::with('repository')->find($siteId);

        if (! $site) {
            $this->telegram->answerCallbackQuery($callbackQueryId, 'Site not found', true);

            return response()->json(['status' => 'not_found']);
        }

        $task = Task::create([
            'title' => "Work on {$site->name}",
            'user_id' => 1,
            'site_id' => $siteId,
            'repository_id' => $site->repository_id,
            'status' => TaskStatus::Pending,
        ]);

        $this->telegram->answerCallbackQuery($callbackQueryId, 'Task created!');

        $keyboard = [
            [
                ['text' => 'View Task', 'callback_data' => "task:view:{$task->id}"],
                ['text' => 'Back to Sites', 'callback_data' => 'menu:sites'],
            ],
        ];

        $this->telegram->sendMessage(
            "Task created for *{$site->name}*!\n\n*UUID:* `{$task->uuid}`",
            $keyboard
        );

        return response()->json(['status' => 'ok', 'task_id' => $task->id]);
    }

    private function handleReplyCallback(array $parts, string $callbackQueryId, string $chatId): JsonResponse
    {
        $taskId = $parts[1] ?? null;

        if (! $taskId) {
            $this->telegram->answerCallbackQuery($callbackQueryId, 'Invalid task ID');

            return response()->json(['status' => 'invalid_task']);
        }

        $task = Task::find($taskId);

        if (! $task) {
            $this->telegram->answerCallbackQuery($callbackQueryId, 'Task not found', true);

            return response()->json(['status' => 'not_found']);
        }

        // Set the user into reply mode for this task
        Cache::put("telegram:{$chatId}:reply_mode", $taskId, now()->addMinutes(10));

        $this->telegram->answerCallbackQuery($callbackQueryId);
        $this->telegram->sendMessage(
            "Replying to task *{$task->uuid}*.\n\nSend your message now (you have 10 minutes):",
            [['text' => 'Cancel', 'callback_data' => 'menu:tasks']]
        );

        return response()->json(['status' => 'ok']);
    }

    private function handleReplyMessage(string $chatId, string $text, ?int $replyToMessageId = null): JsonResponse
    {
        // Check if user is in reply mode
        $taskId = Cache::get("telegram:{$chatId}:reply_mode");

        if (! $taskId) {
            // Not in reply mode, show main menu
            $this->telegram->sendMessage(
                "I didn't understand that. Use the menu or reply to a task message.",
                [['text' => 'Open Menu', 'callback_data' => 'menu:main']]
            );

            return response()->json(['status' => 'no_reply_mode']);
        }

        $task = Task::find($taskId);

        if (! $task) {
            Cache::forget("telegram:{$chatId}:reply_mode");
            $this->telegram->sendMessage('Task not found. Reply mode cancelled.');

            return response()->json(['status' => 'task_not_found']);
        }

        // Create the message in the task
        $message = Message::create([
            'task_id' => $task->id,
            'role' => MessageRole::User,
            'status' => MessageStatus::Sent,
            'content' => $text,
            'from_telegram' => true,
        ]);

        // Clear reply mode
        Cache::forget("telegram:{$chatId}:reply_mode");

        // Dispatch the message to Claude
        $task->dispatchMessage($message);

        // Confirm to user
        $keyboard = [
            [
                ['text' => 'View Task', 'callback_data' => "task:view:{$task->id}"],
                ['text' => 'Reply Again', 'callback_data' => "reply:{$task->id}"],
            ],
        ];

        $this->telegram->sendMessage(
            "Message sent to task *{$task->uuid}*. Claude is processing...",
            $keyboard
        );

        return response()->json(['status' => 'ok', 'message_id' => $message->id]);
    }

    private function commandViewTask(array $args): JsonResponse
    {
        if (empty($args[0])) {
            $this->telegram->sendMessage('Usage: /task <uuid>');

            return response()->json(['status' => 'invalid_args']);
        }

        $uuid = $args[0];
        $task = Task::where('uuid', $uuid)->first();

        if (! $task) {
            $this->telegram->sendMessage("Task with UUID `{$uuid}` not found.");

            return response()->json(['status' => 'not_found']);
        }

        return $this->menuService->showTaskDetails($task->id)
            ? response()->json(['status' => 'ok'])
            : response()->json(['status' => 'error']);
    }
}
