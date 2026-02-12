<?php

namespace App\Services;

use App\Enums\ProposalStatus;
use App\Enums\TaskStatus;
use App\Models\AiProvider;
use App\Models\Proposal;
use App\Models\Repository;
use App\Models\Site;
use App\Models\Task;
use App\Models\TaskSchedule;
use Telegram\Bot\Objects\Message as TelegramMessage;

class TelegramMenuService
{
    private TelegramService $telegram;

    private string $adminChatId;

    public function __construct(TelegramService $telegram)
    {
        $this->telegram = $telegram;
        $this->adminChatId = config('telegram.admin_chat_id');
    }

    /**
     * Show the main menu with all available sections
     */
    public function showMainMenu(): ?TelegramMessage
    {
        $keyboard = [
            [['text' => 'Tasks'], ['text' => 'Proposals']],
            [['text' => 'Repositories'], ['text' => 'Sites']],
            [['text' => 'AI Providers'], ['text' => 'System']],
        ];

        $text = "*Claude Runner Main Menu*\n\nSelect a section to continue:";

        return $this->telegram->sendMessage($text, $keyboard, isReplyKeyboard: true);
    }

    /**
     * Show the Tasks submenu
     */
    public function showTasksMenu(): ?TelegramMessage
    {
        $runningCount = Task::where('status', TaskStatus::Running)->count();
        $pendingCount = Task::where('status', TaskStatus::Pending)->count();
        $todayCount = Task::whereDate('created_at', today())->count();

        $keyboard = [
            [['text' => 'Running Tasks'], ['text' => 'Recent Tasks']],
            [['text' => 'New Task'], ['text' => 'Search Task']],
            [['text' => 'Back to Main Menu']],
        ];

        $text = <<<TEXT
*Tasks Menu*

Running: {$runningCount}
Pending: {$pendingCount}
Created Today: {$todayCount}

What would you like to do?
TEXT;

        return $this->telegram->sendMessage($text, $keyboard, isReplyKeyboard: true);
    }

    /**
     * Show list of running tasks
     */
    public function showRunningTasks(): ?TelegramMessage
    {
        $tasks = Task::where('status', TaskStatus::Running)
            ->with(['repository', 'site'])
            ->orderBy('started_at', 'desc')
            ->take(10)
            ->get();

        if ($tasks->isEmpty()) {
            $keyboard = [['text' => '🔙 Back to Tasks', 'callback_data' => 'menu:tasks']];

            return $this->telegram->sendMessage('✅ No running tasks.', $keyboard);
        }

        // Send header message
        $this->telegram->sendMessage('*Running Tasks:*');

        // Send each task as a separate message with inline buttons
        foreach ($tasks as $task) {
            $project = $task->repository?->name ?? $task->site?->name ?? 'General';
            $duration = $task->started_at?->diffForHumans(now(), true) ?? 'Unknown';

            $text = "🟡 *{$task->title}*\n";
            $text .= "Project: `{$project}`\n";
            $text .= "Duration: {$duration}";

            $keyboard = [
                [
                    ['text' => '👁️ View', 'callback_data' => "task:view:{$task->id}"],
                    ['text' => '❌ Cancel', 'callback_data' => "task:cancel:{$task->id}"],
                ],
            ];

            $this->telegram->sendMessage($text, $keyboard);
        }

        // Send back button at the end
        $backKeyboard = [['text' => '🔙 Back to Tasks', 'callback_data' => 'menu:tasks']];

        return $this->telegram->sendMessage('_End of running tasks_', $backKeyboard);
    }

    /**
     * Show list of recent tasks
     */
    public function showRecentTasks(): ?TelegramMessage
    {
        $tasks = Task::with(['repository', 'site'])
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get();

        if ($tasks->isEmpty()) {
            $keyboard = [['text' => '🔙 Back to Tasks', 'callback_data' => 'menu:tasks']];

            return $this->telegram->sendMessage('📭 No tasks found.', $keyboard);
        }

        // Send header message
        $this->telegram->sendMessage('*📋 Recent Tasks:*');

        // Send each task as a separate message with inline buttons
        foreach ($tasks as $task) {
            $emoji = match ($task->status) {
                TaskStatus::Completed => '🟢',
                TaskStatus::Running => '🟡',
                TaskStatus::Failed => '🔴',
                TaskStatus::Pending => '⚪',
                TaskStatus::WaitingForInput => '⏸️',
                default => '⚪',
            };

            $project = $task->repository?->name ?? $task->site?->name ?? 'General';

            $text = "{$emoji} *{$task->title}*\n";
            $text .= "Status: {$task->status->label()}\n";
            $text .= "Project: `{$project}`";

            $keyboard = [
                [
                    ['text' => '👁️ View', 'callback_data' => "task:view:{$task->id}"],
                ],
            ];

            // Add cancel button for running tasks
            if ($task->isRunning()) {
                $keyboard[0][] = ['text' => '❌ Cancel', 'callback_data' => "task:cancel:{$task->id}"];
            }

            $this->telegram->sendMessage($text, $keyboard);
        }

        // Send back button at the end
        $backKeyboard = [['text' => '🔙 Back to Tasks', 'callback_data' => 'menu:tasks']];

        return $this->telegram->sendMessage('_End of recent tasks_', $backKeyboard);
    }

    /**
     * Show task details with actions
     */
    public function showTaskDetails(int $taskId): ?TelegramMessage
    {
        $task = Task::with(['repository', 'site', 'messages'])->find($taskId);

        if (! $task) {
            return $this->telegram->sendMessage('Task not found.');
        }

        $project = $task->repository?->name ?? $task->site?->name ?? 'General Chat';
        $duration = $task->started_at && $task->completed_at
            ? $task->started_at->diffForHumans($task->completed_at, true)
            : ($task->started_at ? $task->started_at->diffForHumans(now(), true) : 'Not started');

        $messageCount = $task->messages->count();
        $lastMessage = $task->messages->last()?->created_at?->diffForHumans() ?? 'No messages';

        // Status emoji
        $statusEmoji = match ($task->status) {
            TaskStatus::Completed => '🟢',
            TaskStatus::Running => '🟡',
            TaskStatus::Failed => '🔴',
            TaskStatus::Pending => '⚪',
            TaskStatus::WaitingForInput => '⏸️',
            default => '⚪',
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
                ['text' => '❌ Cancel Task', 'callback_data' => "task:cancel:{$task->id}"],
            ];
        }

        if ($messageCount > 0) {
            $keyboard[] = [
                ['text' => '💬 View Messages', 'callback_data' => "task:messages:{$task->id}"],
            ];
        }

        $keyboard[] = [['text' => '🔙 Back to Tasks', 'callback_data' => 'menu:tasks']];

        return $this->telegram->sendMessage($text, $keyboard);
    }

    /**
     * Show the Proposals submenu
     */
    public function showProposalsMenu(): ?TelegramMessage
    {
        $pendingCount = Proposal::where('status', ProposalStatus::Pending)->count();
        $approvedCount = Proposal::where('status', ProposalStatus::Approved)->whereDate('updated_at', today())->count();

        $keyboard = [
            [['text' => 'Pending Proposals'], ['text' => 'All Proposals']],
            [['text' => 'Back to Main Menu']],
        ];

        $text = <<<TEXT
*Proposals Menu*

Pending: {$pendingCount}
Approved Today: {$approvedCount}

What would you like to do?
TEXT;

        return $this->telegram->sendMessage($text, $keyboard, isReplyKeyboard: true);
    }

    /**
     * Show pending proposals
     */
    public function showPendingProposals(): ?TelegramMessage
    {
        $proposals = Proposal::where('status', ProposalStatus::Pending)
            ->orderBy('priority', 'desc')
            ->orderBy('created_at', 'asc')
            ->take(10)
            ->get();

        if ($proposals->isEmpty()) {
            $keyboard = [
                [['text' => 'Back to Proposals', 'callback_data' => 'menu:proposals']],
            ];

            return $this->telegram->sendMessage('No pending proposals.', $keyboard);
        }

        foreach ($proposals as $proposal) {
            $keyboard = [
                [
                    ['text' => 'Approve', 'callback_data' => "proposal:approve:{$proposal->id}"],
                    ['text' => 'Reject', 'callback_data' => "proposal:reject:{$proposal->id}"],
                ],
                [
                    ['text' => 'View Details', 'callback_data' => "proposal:details:{$proposal->id}"],
                ],
            ];

            $this->telegram->sendMessage($proposal->formatForTelegram(), $keyboard);
        }

        $backKeyboard = [
            [['text' => 'Back to Proposals', 'callback_data' => 'menu:proposals']],
        ];

        return $this->telegram->sendMessage('_End of pending proposals_', $backKeyboard);
    }

    /**
     * Show the Repositories submenu
     */
    public function showRepositoriesMenu(): ?TelegramMessage
    {
        $count = Repository::count();
        $recentCount = Repository::whereDate('last_synced_at', today())->count();

        $keyboard = [
            [['text' => 'List Repositories'], ['text' => 'Sync All']],
            [['text' => 'Back to Main Menu']],
        ];

        $text = <<<TEXT
*Repositories Menu*

Total: {$count}
Synced Today: {$recentCount}

What would you like to do?
TEXT;

        return $this->telegram->sendMessage($text, $keyboard, isReplyKeyboard: true);
    }

    /**
     * Show list of repositories
     */
    public function showRepositories(): ?TelegramMessage
    {
        $repos = Repository::orderBy('name')->take(20)->get();

        if ($repos->isEmpty()) {
            return $this->telegram->sendMessage('No repositories found.');
        }

        $keyboard = [];
        $text = "*Repositories:*\n\n";

        foreach ($repos as $repo) {
            $synced = $repo->last_synced_at?->diffForHumans() ?? 'Never';
            $text .= "*{$repo->name}*\n";
            $text .= "Branch: `{$repo->default_branch}`\n";
            $text .= "Last Sync: {$synced}\n\n";

            $keyboard[] = [
                ['text' => "New Task: {$repo->name}", 'callback_data' => "repo:task:{$repo->id}"],
            ];
        }

        $keyboard[] = [['text' => 'Back to Repositories', 'callback_data' => 'menu:repos']];

        return $this->telegram->sendMessage($text, $keyboard);
    }

    /**
     * Show the Sites submenu
     */
    public function showSitesMenu(): ?TelegramMessage
    {
        $count = Site::count();

        $keyboard = [
            [['text' => 'List Sites'], ['text' => 'Deploy Site']],
            [['text' => 'Back to Main Menu']],
        ];

        $text = <<<TEXT
*Sites Menu*

Total Sites: {$count}

What would you like to do?
TEXT;

        return $this->telegram->sendMessage($text, $keyboard, isReplyKeyboard: true);
    }

    /**
     * Show list of sites
     */
    public function showSites(): ?TelegramMessage
    {
        $sites = Site::with('repository')->orderBy('name')->take(20)->get();

        if ($sites->isEmpty()) {
            return $this->telegram->sendMessage('No sites found.');
        }

        $keyboard = [];
        $text = "*Sites:*\n\n";

        foreach ($sites as $site) {
            $repoName = $site->repository?->name ?? 'N/A';
            $text .= "*{$site->name}*\n";
            $text .= "Domain: `{$site->domain}`\n";
            $text .= "Repo: {$repoName}\n\n";

            $keyboard[] = [
                ['text' => "Deploy: {$site->name}", 'callback_data' => "site:deploy:{$site->id}"],
                ['text' => 'New Task', 'callback_data' => "site:task:{$site->id}"],
            ];
        }

        $keyboard[] = [['text' => 'Back to Sites', 'callback_data' => 'menu:sites']];

        return $this->telegram->sendMessage($text, $keyboard);
    }

    /**
     * Show the AI Providers submenu
     */
    public function showAiProvidersMenu(): ?TelegramMessage
    {
        $providers = AiProvider::where('is_active', true)->get();
        $defaultProvider = AiProvider::getDefault();

        $keyboard = [
            [['text' => 'View Providers'], ['text' => 'Switch Provider']],
            [['text' => 'Back to Main Menu']],
        ];

        $defaultName = $defaultProvider?->display_name ?? 'None';
        $text = <<<TEXT
*AI Providers Menu*

Active Providers: {$providers->count()}
Default: {$defaultName}

What would you like to do?
TEXT;

        return $this->telegram->sendMessage($text, $keyboard, isReplyKeyboard: true);
    }

    /**
     * Show AI providers with quotas
     */
    public function showAiProviders(): ?TelegramMessage
    {
        $providers = AiProvider::orderBy('name')->get();

        if ($providers->isEmpty()) {
            return $this->telegram->sendMessage('No AI providers configured.');
        }

        $text = "*AI Providers:*\n\n";

        foreach ($providers as $provider) {
            $status = $provider->is_active ? '' : '';
            $default = $provider->is_default ? ' (Default)' : '';
            $quota = $provider->quota_limit
                ? "{$provider->quota_used} / {$provider->quota_limit} tokens"
                : 'No quota';

            $text .= "{$status} *{$provider->display_name}*{$default}\n";
            $text .= "Model: `{$provider->model}`\n";
            $text .= "Quota: {$quota}\n\n";
        }

        $keyboard = [
            [['text' => 'Back to AI Providers', 'callback_data' => 'menu:providers']],
        ];

        return $this->telegram->sendMessage($text, $keyboard);
    }

    /**
     * Show the System submenu
     */
    public function showSystemMenu(): ?TelegramMessage
    {
        $schedulesCount = TaskSchedule::count();
        $failedTasks = Task::where('status', TaskStatus::Failed)->whereDate('created_at', today())->count();

        $keyboard = [
            [['text' => 'System Status'], ['text' => 'Schedules']],
            [['text' => 'Back to Main Menu']],
        ];

        $text = <<<TEXT
*System Menu*

Scheduled Tasks: {$schedulesCount}
Failed Today: {$failedTasks}

What would you like to check?
TEXT;

        return $this->telegram->sendMessage($text, $keyboard, isReplyKeyboard: true);
    }

    /**
     * Show comprehensive system status
     */
    public function showSystemStatus(): ?TelegramMessage
    {
        $pendingProposals = Proposal::where('status', ProposalStatus::Pending)->count();
        $runningTasks = Task::where('status', TaskStatus::Running)->count();
        $completedToday = Task::where('status', TaskStatus::Completed)
            ->whereDate('completed_at', today())
            ->count();
        $failedToday = Task::where('status', TaskStatus::Failed)
            ->whereDate('created_at', today())
            ->count();

        $text = <<<TEXT
*System Status*

*Tasks:*
Running: {$runningTasks}
Completed Today: {$completedToday}
Failed Today: {$failedToday}

*Proposals:*
Pending: {$pendingProposals}

*Repositories:* {Repository::count()}
*Sites:* {Site::count()}
*Schedules:* {TaskSchedule::count()}
TEXT;

        $keyboard = [
            [['text' => 'Refresh', 'callback_data' => 'system:status']],
            [['text' => 'Back to System', 'callback_data' => 'menu:system']],
        ];

        return $this->telegram->sendMessage($text, $keyboard);
    }

    /**
     * Show scheduled tasks
     */
    public function showSchedules(): ?TelegramMessage
    {
        $schedules = TaskSchedule::with(['repository', 'site'])->take(10)->get();

        if ($schedules->isEmpty()) {
            return $this->telegram->sendMessage('No scheduled tasks configured.');
        }

        $text = "*Scheduled Tasks:*\n\n";

        foreach ($schedules as $schedule) {
            $project = $schedule->repository?->name ?? $schedule->site?->name ?? 'General';
            $lastRun = $schedule->last_run_at?->diffForHumans() ?? 'Never';
            $status = $schedule->last_run_status ?? 'N/A';

            $text .= "*{$schedule->name}*\n";
            $text .= "Project: `{$project}`\n";
            $text .= "Frequency: {$schedule->frequency}\n";
            $text .= "Last Run: {$lastRun} ({$status})\n\n";
        }

        $keyboard = [
            [['text' => 'Back to System', 'callback_data' => 'menu:system']],
        ];

        return $this->telegram->sendMessage($text, $keyboard);
    }

    /**
     * Prompt for new task creation
     */
    public function promptNewTask(): ?TelegramMessage
    {
        $text = <<<'TEXT'
*Create New Task*

Please send me the task title in the format:

`New Task: Your task title here`

Or select a repository first:
TEXT;

        $repos = Repository::orderBy('name')->take(5)->get();
        $keyboard = [];

        foreach ($repos as $repo) {
            $keyboard[] = [
                ['text' => "Use: {$repo->name}", 'callback_data' => "newtask:repo:{$repo->id}"],
            ];
        }

        $keyboard[] = [['text' => 'General Chat (No Repo)', 'callback_data' => 'newtask:general']];
        $keyboard[] = [['text' => 'Cancel', 'callback_data' => 'menu:tasks']];

        return $this->telegram->sendMessage($text, $keyboard);
    }

    /**
     * Handle text input for new task
     */
    public function handleNewTaskInput(string $text): ?TelegramMessage
    {
        $title = str_replace('New Task:', '', $text);
        $title = trim($title);

        if (empty($title)) {
            return $this->telegram->sendMessage('Task title cannot be empty. Please try again.');
        }

        $task = Task::create([
            'title' => $title,
            'user_id' => 1,
            'status' => TaskStatus::Pending,
        ]);

        $keyboard = [
            [
                ['text' => 'View Task', 'callback_data' => "task:view:{$task->id}"],
                ['text' => 'Back to Tasks', 'callback_data' => 'menu:tasks'],
            ],
        ];

        return $this->telegram->sendMessage(
            "Task created successfully!\n\n*UUID:* `{$task->uuid}`\n*Title:* {$task->title}",
            $keyboard
        );
    }
}
