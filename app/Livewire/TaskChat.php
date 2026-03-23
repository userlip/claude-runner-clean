<?php

namespace App\Livewire;

use App\Enums\MessageRole;
use App\Enums\MessageStatus;
use App\Enums\TaskStatus;
use App\Jobs\RunRalphJob;
use App\Models\AiProvider;
use App\Models\Message;
use App\Models\RepositoryEnvConfig;
use App\Models\Task;
use App\Services\RalphWorkspaceService;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class TaskChat extends Component
{
    private const int MESSAGE_BATCH_SIZE = 100;

    public Task $task;

    public string $prompt = '';

    public string $chatMode = 'normal';

    /** @var array<int, array{data: string, name: string}> */
    public array $images = [];

    public bool $waitingForResponse = false;

    public int $lastMessageCount = 0;

    /**
     * To improve perceived performance (especially in SPA navigation), defer
     * loading the message list until the browser has painted the shell.
     */
    public bool $messagesLoaded = false;

    public int $visibleMessageCount = 0;

    public bool $showDeployModal = false;

    public string $deploySubdomain = '';

    public string $deployPhpVersion = '8.4';

    public string $deployWebDirectory = '/public';

    public ?string $deployDatabaseName = null;

    public bool $showAdvancedOptions = false;

    /**
     * Message IDs that should show all blocks (not truncated).
     *
     * @var array<int, bool>
     */
    public array $expandedMessageIds = [];

    public function mount(Task $task): void
    {
        $this->task = $task;
        $this->lastMessageCount = $task->messages()->count();
        $task->markAsViewed();
    }

    public function loadMessages(): void
    {
        $this->messagesLoaded = true;
        $this->visibleMessageCount = min($this->sentMessageCount(), self::MESSAGE_BATCH_SIZE);
        unset($this->chatMessages, $this->totalMessageCount, $this->hasHiddenMessages);
        $this->dispatch('messages-loaded');
    }

    public function loadMoreMessages(): void
    {
        if (! $this->messagesLoaded) {
            $this->loadMessages();

            return;
        }

        $totalMessageCount = $this->sentMessageCount();
        $nextVisibleCount = min($totalMessageCount, $this->visibleMessageCount + self::MESSAGE_BATCH_SIZE);

        if ($nextVisibleCount === $this->visibleMessageCount) {
            return;
        }

        $this->visibleMessageCount = $nextVisibleCount;
        unset($this->chatMessages, $this->totalMessageCount, $this->hasHiddenMessages);

        $this->dispatch('chat-history-prepended');
    }

    #[On('insert-snippet')]
    public function insertSnippet(string $content): void
    {
        if (! empty($this->prompt)) {
            $this->prompt .= "\n\n";
        }
        $this->prompt .= $content;
    }

    /**
     * @return Collection<int, Message>
     */
    #[Computed]
    public function chatMessages(): Collection
    {
        if (! $this->messagesLoaded) {
            return new Collection;
        }

        $totalMessageCount = $this->sentMessageCount();
        $visibleMessageCount = $this->visibleMessageCount > 0
            ? min($this->visibleMessageCount, $totalMessageCount)
            : min($totalMessageCount, self::MESSAGE_BATCH_SIZE);
        $hiddenMessageCount = max(0, $totalMessageCount - $visibleMessageCount);

        return $this->sentMessagesQuery()
            ->oldest()
            ->skip($hiddenMessageCount)
            ->take($visibleMessageCount)
            ->orderBy('id')
            ->get();
    }

    /**
     * Total count of sent messages.
     */
    #[Computed]
    public function totalMessageCount(): int
    {
        if (! $this->messagesLoaded) {
            return 0;
        }

        return $this->sentMessageCount();
    }

    #[Computed]
    public function hasHiddenMessages(): bool
    {
        if (! $this->messagesLoaded) {
            return false;
        }

        return $this->visibleMessageCount < $this->totalMessageCount;
    }

    /**
     * Toggle showing all blocks for a message (expand truncated blocks).
     */
    public function toggleExpandMessage(int $messageId): void
    {
        if (isset($this->expandedMessageIds[$messageId])) {
            unset($this->expandedMessageIds[$messageId]);
        } else {
            $this->expandedMessageIds[$messageId] = true;
        }
    }

    /**
     * @return Collection<int, Message>
     */
    #[Computed]
    public function queuedMessages(): Collection
    {
        return $this->task->messages()
            ->where('status', MessageStatus::Queued)
            ->oldest()
            ->get();
    }

    #[Computed]
    public function isRunning(): bool
    {
        return $this->task->isRunning();
    }

    #[Computed]
    public function isWaitingForInput(): bool
    {
        return $this->task->isWaitingForInput();
    }

    /**
     * Called by wire:poll to check if we should continue polling.
     * This method updates the waitingForResponse state.
     */
    public function checkPolling(): void
    {
        // Refresh task status
        $this->task->refresh();

        // Only stop waiting when task is no longer running
        // (message count check is unreliable since empty assistant message is created immediately)
        // Also stop if waiting for input (AskUserQuestion detected)
        if ($this->waitingForResponse && ! $this->task->isRunning()) {
            $this->waitingForResponse = false;
            $this->lastMessageCount = $this->task->messages()->count();
        }
    }

    #[Computed]
    public function hasActiveSubagents(): bool
    {
        return $this->task->has_active_subagents ?? false;
    }

    #[Computed]
    public function shouldPoll(): bool
    {
        return $this->isRunning || $this->waitingForResponse || $this->hasActiveSubagents || $this->task->ralph_enabled;
    }

    #[Computed]
    public function locationLabel(): string
    {
        if ($this->task->site) {
            return $this->task->site->domain;
        }

        if ($this->task->isGeneralChat()) {
            return $this->task->working_directory;
        }

        return 'Workspace';
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
        return $this->task->aiProvider;
    }

    #[Computed]
    public function providerLabel(): string
    {
        return $this->currentProvider?->display_name ?? 'Claude';
    }

    #[Computed]
    public function envConfigs(): EloquentCollection
    {
        if (! $this->task->repository) {
            return new EloquentCollection;
        }

        return $this->task->repository->envConfigs()->orderByDesc('is_default')->get();
    }

    #[Computed]
    public function hasEnvConfigs(): bool
    {
        return $this->envConfigs->isNotEmpty();
    }

    #[Computed]
    public function defaultEnvConfig(): ?RepositoryEnvConfig
    {
        return $this->envConfigs->firstWhere('is_default', true);
    }

    #[Computed]
    public function contextUsed(): int
    {
        $lastAssistantMessage = $this->task->messages()
            ->where('role', MessageRole::Assistant)
            ->whereNotNull('tokens_in')
            ->latest()
            ->first();

        return $lastAssistantMessage?->tokens_in ?? 0;
    }

    #[Computed]
    public function contextLimit(): int
    {
        return $this->task->aiProvider?->getContextWindow() ?? 200000;
    }

    #[Computed]
    public function contextPercentage(): float
    {
        if ($this->contextLimit === 0) {
            return 0;
        }

        return ($this->contextUsed / $this->contextLimit) * 100;
    }

    #[Computed]
    public function contextColor(): string
    {
        $percentage = $this->contextPercentage;

        if ($percentage >= 80) {
            return 'chat-context-fill-high';
        }

        if ($percentage >= 60) {
            return 'chat-context-fill-medium';
        }

        return 'chat-context-fill-low';
    }

    /**
     * Get the current Ralph loop status for display in the chat header.
     *
     * @return array{iteration: int, stories_passed: int, stories_total: int, status: string}|null
     */
    #[Computed]
    public function ralphStatus(): ?array
    {
        if (! $this->task->ralph_enabled) {
            return null;
        }

        try {
            $ralph = app(RalphWorkspaceService::class);
            $state = $ralph->readState($this->task);

            $passed = collect($state->prd['userStories'] ?? [])
                ->filter(fn ($s) => $s['passes'] ?? false)
                ->count();
            $total = count($state->prd['userStories'] ?? []);

            // Determine Ralph-specific status from actual state, not task status
            if ($total > 0 && $passed >= $total) {
                $ralphStatus = 'completed';
            } elseif ($this->task->ralph_stopped_reason) {
                $ralphStatus = 'failed';
            } elseif ($this->isRalphStalled()) {
                $ralphStatus = 'stalled';
            } else {
                $ralphStatus = 'running';
            }

            return [
                'iteration' => $this->task->ralph_iteration,
                'stories_passed' => $passed,
                'stories_total' => $total,
                'status' => $ralphStatus,
            ];
        } catch (\Exception) {
            return [
                'iteration' => $this->task->ralph_iteration,
                'stories_passed' => 0,
                'stories_total' => 0,
                'status' => 'running',
            ];
        }
    }

    /**
     * Check if the Ralph loop has stalled (enabled but no job in the queue).
     * Cached for 10 seconds to avoid LIKE scanning the jobs table on every poll.
     */
    protected function isRalphStalled(): bool
    {
        return \Cache::remember(
            "ralph_stalled_{$this->task->id}",
            10,
            function () {
                $hasJob = \DB::table('jobs')
                    ->where('payload', 'like', '%RunRalphJob%')
                    ->where('payload', 'like', "%{$this->task->id}%")
                    ->exists();

                return ! $hasJob;
            }
        );
    }

    public function setProvider(int $providerId): void
    {
        $provider = AiProvider::where('is_active', true)->find($providerId);

        if ($provider) {
            $this->task->update(['ai_provider_id' => $provider->id]);
            $this->task->refresh();
        }
    }

    public function sendMessage(): void
    {
        // Allow sending with just images (no text required)
        $hasContent = ! empty(trim($this->prompt)) || ! empty($this->images);
        $plainPrompt = trim($this->prompt);

        if (! $hasContent) {
            return;
        }

        $this->validate([
            'prompt' => 'nullable|string',
            'images' => 'array|max:10',
            'images.*.data' => 'required|string',
            'images.*.name' => 'required|string|max:255',
        ]);

        $previousSentMessageCount = $this->messagesLoaded ? $this->sentMessageCount() : 0;

        // Refresh task to get latest status before checking isRunning
        $this->task->refresh();

        // Handle local chat commands (only when not running)
        if (! $this->task->isRunning() && ! empty($this->prompt) && $this->handleLocalCommand($this->prompt)) {
            $this->prompt = '';
            $this->images = [];

            return;
        }

        // If Claude is running, queue the message instead of sending immediately
        if ($this->task->isRunning()) {
            Message::create([
                'task_id' => $this->task->id,
                'role' => MessageRole::User,
                'status' => MessageStatus::Queued,
                'content' => $this->prompt ?: '',
                'images' => ! empty($this->images) ? $this->images : null,
            ]);

            $this->prompt = '';
            $this->images = [];

            return;
        }

        // Check if there's a successful assistant response to continue from
        $hasSuccessfulResponse = $this->task->messages()
            ->where('role', MessageRole::Assistant)
            ->where('status', MessageStatus::Sent)
            ->whereNotNull('content')
            ->where('content', '!=', '')
            ->where('content', 'not like', 'Error:%')
            ->exists();

        $content = $this->prompt ?: '';

        // Prepend mode system prompt on first message if a mode is selected
        if ($this->chatMode !== 'normal' && ! $hasSuccessfulResponse) {
            $modePrompt = $this->getModePrompt($this->chatMode);
            if ($modePrompt) {
                $content = $modePrompt."\n\n---\n\n".$content;
            }
        }

        $userMessage = Message::create([
            'task_id' => $this->task->id,
            'role' => MessageRole::User,
            'status' => MessageStatus::Sent,
            'content' => $content,
            'images' => ! empty($this->images) ? $this->images : null,
        ]);

        if ($this->shouldAutoGenerateTitle($plainPrompt)) {
            $this->generateTitle(notify: false);
        }

        $this->task->dispatchMessage($userMessage, continue: $hasSuccessfulResponse);

        $this->expandVisibleWindowIfFullyLoaded($previousSentMessageCount);

        $this->prompt = '';
        $this->images = [];
        $this->waitingForResponse = true;
        $this->lastMessageCount = $this->task->messages()->where('status', MessageStatus::Sent)->count();
    }

    protected function shouldAutoGenerateTitle(string $plainPrompt): bool
    {
        return blank($this->task->title) && $plainPrompt !== '';
    }

    /**
     * Handle local commands locally without sending to Claude.
     */
    protected function handleLocalCommand(string $prompt): bool
    {
        $command = strtolower(trim($prompt));

        if (in_array($command, ['start ralph', '/start ralph'], true)) {
            $this->startRalphLoop();

            return true;
        }

        if (in_array($command, ['restart ralph', '/restart ralph'], true)) {
            $this->restartRalphLoop();

            return true;
        }

        if ($command === '/usage') {
            $this->handleUsageCommand();

            return true;
        }

        if ($command === '/clear') {
            $this->handleClearCommand();

            return true;
        }

        if ($command === '/help') {
            $this->handleHelpCommand();

            return true;
        }

        if ($command === '/context') {
            $this->handleContextCommand();

            return true;
        }

        if ($command === '/skills') {
            $this->handleHelpCommand();

            return true;
        }

        // /compact is NOT handled locally - it needs to be sent to Claude
        // to trigger actual context compaction in the session
        if (str_starts_with($command, '/compact')) {
            return false; // Let it pass through to Claude
        }

        // /prd-to-issues <number> - prepend skill prompt and send to Claude
        if (str_starts_with($command, '/prd-to-issues')) {
            $this->prompt = $this->buildPrdToIssuesPrompt($prompt);

            return false; // Let it pass through to Claude with the modified prompt
        }

        return false;
    }

    protected function handleUsageCommand(): void
    {
        $stats = $this->task->messages()
            ->where('role', MessageRole::Assistant)
            ->selectRaw('SUM(tokens_in) as total_in, SUM(tokens_out) as total_out, SUM(cost_usd) as total_cost')
            ->first();

        $totalIn = $stats->total_in ?? 0;
        $totalOut = $stats->total_out ?? 0;
        $totalCost = $stats->total_cost ?? 0;
        $messageCount = $this->task->messages()->count();

        $content = "## Session Usage\n\n";
        $content .= "| Metric | Value |\n";
        $content .= "|--------|-------|\n";
        $content .= "| Messages | {$messageCount} |\n";
        $content .= '| Input Tokens | '.number_format($totalIn)." |\n";
        $content .= '| Output Tokens | '.number_format($totalOut)." |\n";
        $content .= '| Total Tokens | '.number_format($totalIn + $totalOut)." |\n";
        $content .= '| Cost | $'.number_format($totalCost, 4)." |\n";

        // Create system message for usage
        Message::create([
            'task_id' => $this->task->id,
            'role' => MessageRole::User,
            'content' => '/usage',
        ]);

        Message::create([
            'task_id' => $this->task->id,
            'role' => MessageRole::Assistant,
            'content' => $content,
        ]);
    }

    protected function handleClearCommand(): void
    {
        $this->task->messages()->delete();
        $this->lastMessageCount = 0;

        $this->dispatch('notify', [
            'message' => 'Conversation cleared.',
        ]);
    }

    protected function handleHelpCommand(): void
    {
        $content = "## Available Commands\n\n";
        $content .= "| Command | Description |\n";
        $content .= "|---------|-------------|\n";
        $content .= "| `/usage` | Show token usage and cost for this session |\n";
        $content .= "| `/context` | Show context window usage |\n";
        $content .= "| `/compact` | Compact conversation to reduce context usage |\n";
        $content .= "| `/prd-to-issues <number>` | Break a PRD issue into vertical slice GitHub issues |\n";
        $content .= "| `/clear` | Clear all messages in this conversation |\n";
        $content .= "| `/help`, `/skills` | Show this help message |\n";

        Message::create([
            'task_id' => $this->task->id,
            'role' => MessageRole::User,
            'content' => '/help',
        ]);

        Message::create([
            'task_id' => $this->task->id,
            'role' => MessageRole::Assistant,
            'content' => $content,
        ]);
    }

    protected function handleContextCommand(): void
    {
        $used = $this->contextUsed;
        $limit = $this->contextLimit;
        $percentage = $this->contextPercentage;
        $remaining = $limit - $used;

        $status = $percentage >= 80 ? 'Critical' : ($percentage >= 60 ? 'Warning' : 'Good');

        $content = "## Context Window Usage\n\n";
        $content .= "| Metric | Value |\n";
        $content .= "|--------|-------|\n";
        $content .= '| Used | '.number_format($used)." tokens |\n";
        $content .= '| Remaining | '.number_format($remaining)." tokens |\n";
        $content .= '| Limit | '.number_format($limit)." tokens |\n";
        $content .= '| Usage | '.number_format($percentage, 1)."% |\n";
        $content .= "| Status | {$status} |\n";

        Message::create([
            'task_id' => $this->task->id,
            'role' => MessageRole::User,
            'content' => '/context',
        ]);

        Message::create([
            'task_id' => $this->task->id,
            'role' => MessageRole::Assistant,
            'content' => $content,
        ]);
    }

    /**
     * Trigger the PRD-to-Issues workflow by sending the skill prompt to Claude.
     * Called from the header button.
     */
    public function triggerPrdToIssues(): void
    {
        $this->prompt = $this->buildPrdToIssuesPrompt('/prd-to-issues');
        $this->sendMessage();
    }

    /**
     * Start the Ralph loop by importing PRD slice issues from GitHub and dispatching RunRalphJob.
     * Scans recent messages to find the parent PRD issue number automatically.
     */
    public function startRalphLoop(): void
    {
        // Refresh from DB to prevent duplicate starts from rapid clicks
        $this->task->refresh();

        if (! $this->task->repository) {
            Notification::make()
                ->title('Task must be linked to a repository')
                ->danger()
                ->send();

            return;
        }

        if ($this->task->ralph_enabled) {
            Notification::make()
                ->title('Ralph loop is already running')
                ->warning()
                ->send();

            return;
        }

        // Lock immediately to prevent duplicate starts from rapid clicks
        $this->task->update(['ralph_enabled' => true]);

        // Find PRD parent issue number from recent messages
        $prdIssueNumber = $this->detectPrdIssueNumber();

        if (! $prdIssueNumber) {
            $this->task->update(['ralph_enabled' => false]);
            Notification::make()
                ->title('Could not detect PRD issue number')
                ->body('Use PRD → Issues first to create slice issues, then start Ralph.')
                ->warning()
                ->send();

            return;
        }

        $repo = $this->task->repository;
        $repoFullName = $repo->full_name;

        // Fetch child issues with prd-slice label
        $issuesResult = Process::run(
            "gh issue list --repo {$repoFullName} --label prd-slice --state open --json number,title,body --limit 100"
        );

        if (! $issuesResult->successful()) {
            $this->task->update(['ralph_enabled' => false]);
            Notification::make()
                ->title('Failed to fetch issues from GitHub')
                ->body($issuesResult->errorOutput())
                ->danger()
                ->send();

            return;
        }

        $issues = json_decode($issuesResult->output(), true) ?? [];

        // Filter to only issues that reference the parent PRD
        $parentRef = "#{$prdIssueNumber}";
        $childIssues = collect($issues)->filter(function ($issue) use ($parentRef) {
            return str_contains($issue['body'] ?? '', $parentRef);
        })->values();

        if ($childIssues->isEmpty()) {
            $this->task->update(['ralph_enabled' => false]);
            Notification::make()
                ->title('No PRD slice issues found')
                ->body("No open issues with label 'prd-slice' reference #{$prdIssueNumber}")
                ->warning()
                ->send();

            return;
        }

        // Build user stories from issues
        $userStories = $childIssues->map(function ($issue, $index) {
            $criteria = [];
            if (preg_match_all('/- \[ \] (.+)/m', $issue['body'] ?? '', $matches)) {
                $criteria = $matches[1];
            }

            $blockedBy = [];
            $blockedBySection = $this->extractIssueSection($issue['body'] ?? '', 'Blocked by');
            if (preg_match_all('/#(\d+)/', $blockedBySection, $matches)) {
                $blockedBy = $matches[1];
            }

            return [
                'id' => 'STORY-'.($index + 1),
                'title' => $issue['title'],
                'githubIssue' => $issue['number'],
                'priority' => $index + 1,
                'passes' => false,
                'acceptanceCriteria' => $criteria,
                'blockedBy' => $blockedBy,
            ];
        })->toArray();

        // Initialize Ralph workspace
        $branchName = "ralph/{$this->task->uuid}";
        $verificationCommand = 'php artisan test';

        $ralph = app(RalphWorkspaceService::class);
        $ralph->initialize($this->task, [
            'branch_name' => $branchName,
            'verification_command' => $verificationCommand,
            'stories' => $userStories,
        ]);

        // Write full prd.json with GitHub metadata
        $ralph->updatePrd($this->task, [
            'branchName' => $branchName,
            'verificationCommand' => $verificationCommand,
            'parentIssue' => $prdIssueNumber,
            'userStories' => $userStories,
        ]);

        // Enable Ralph on the task and set status to running so polling kicks in
        $this->task->update([
            'status' => TaskStatus::Running,
            'ralph_enabled' => true,
            'ralph_max_iterations' => 25,
            'ralph_branch_name' => $branchName,
            'ralph_iteration' => 1,
            'ralph_gutter_count' => 0,
        ]);

        $this->waitingForResponse = true;

        // Dispatch the first iteration
        RunRalphJob::dispatch($this->task);

        Notification::make()
            ->title("Ralph loop started with {$childIssues->count()} stories from PRD #{$prdIssueNumber}")
            ->success()
            ->send();
    }

    /**
     * Restart a stalled Ralph loop by re-dispatching from the current iteration.
     */
    public function restartRalphLoop(): void
    {
        $this->task->refresh();

        if (! $this->task->ralph_enabled) {
            return;
        }

        // Reset gutter count and stopped reason so the loop can continue
        $this->task->update([
            'ralph_stopped_reason' => null,
            'ralph_gutter_count' => 0,
        ]);

        RunRalphJob::dispatch($this->task, $this->task->ralph_iteration);

        Notification::make()
            ->title('Ralph loop restarted from iteration #'.$this->task->ralph_iteration)
            ->success()
            ->send();
    }

    /**
     * Detect the PRD parent issue number from recent chat messages.
     * Looks for patterns like "PRD #572" or "PRD issue #572" in assistant messages.
     */
    protected function detectPrdIssueNumber(): ?int
    {
        // 1. Check existing .ralph/ prd files in the workspace
        $ralphDir = $this->task->working_directory.'/.ralph';
        if (is_dir($ralphDir)) {
            // Check prd-<number>.json files first (most explicit)
            $prdFiles = glob($ralphDir.'/prd-*.json');
            foreach ($prdFiles as $file) {
                if (preg_match('/prd-(\d+)\.json$/', $file, $matches)) {
                    return (int) $matches[1];
                }
            }

            // Check parentIssue / parentPrd in prd.json
            $prdJsonPath = $ralphDir.'/prd.json';
            if (file_exists($prdJsonPath)) {
                $prd = json_decode(file_get_contents($prdJsonPath), true);
                if (! empty($prd['prd_issue'])) {
                    return (int) preg_replace('/\D/', '', $prd['prd_issue']);
                }

                if (! empty($prd['parentIssue'])) {
                    return (int) preg_replace('/\D/', '', $prd['parentIssue']);
                }

                if (! empty($prd['parentPrd'])) {
                    return (int) preg_replace('/\D/', '', $prd['parentPrd']);
                }

                if (! empty($prd['prd']['issue_number'])) {
                    return (int) $prd['prd']['issue_number'];
                }

                if (! empty($prd['parent_issue']['number'])) {
                    return (int) $prd['parent_issue']['number'];
                }

                if (! empty($prd['parent_prd']['issue_number'])) {
                    return (int) $prd['parent_prd']['issue_number'];
                }
            }
        }

        // 2. Scan assistant messages for PRD references
        $recentMessages = $this->task->messages()
            ->where('role', MessageRole::Assistant)
            ->latest()
            ->take(10)
            ->pluck('content');

        foreach ($recentMessages as $content) {
            if (! $content) {
                continue;
            }

            // Match "PRD #123", "PRD issue #123", "Parent PRD:** #123", "PRD:** [#123](url)"
            if (preg_match('/PRD(?:[:\s*]+|.*?\b)(?:issue(?:\s+is)?\s*)?(?:created\s+as\s+github\s+issue\s*)?(?:is\s+live\s+as\s+github\s+issue\s*)?(?:\[)?#(\d+)/i', $content, $matches)) {
                return (int) $matches[1];
            }
        }

        // 3. Check user messages for /prd-to-issues <number>
        $userMessages = $this->task->messages()
            ->where('role', MessageRole::User)
            ->latest()
            ->take(10)
            ->pluck('content');

        foreach ($userMessages as $content) {
            if (! $content) {
                continue;
            }

            if (preg_match('/prd-to-issues\s+(\d+)/i', $content, $matches)) {
                return (int) $matches[1];
            }
        }

        return null;
    }

    /**
     * Extract a section from a GitHub issue body by heading.
     */
    protected function extractIssueSection(string $body, string $heading): string
    {
        $pattern = '/## '.preg_quote($heading, '/').'\s*\n(.*?)(?=\n## |\z)/s';
        if (preg_match($pattern, $body, $matches)) {
            return trim($matches[1]);
        }

        return '';
    }

    /**
     * Build the prompt for the PRD-to-Issues skill, prepending the skill instructions.
     */
    protected function buildPrdToIssuesPrompt(string $userInput): string
    {
        // Extract issue number if provided (e.g., "/prd-to-issues 572")
        $parts = preg_split('/\s+/', trim($userInput), 2);
        $issueNumber = $parts[1] ?? '';

        $skillPrompt = <<<'SKILL'
You are running the PRD-to-Issues skill. Break the PRD into independently-grabbable GitHub issues using vertical slices (tracer bullets).

## Process
1. Fetch the PRD issue with `gh issue view <number>` (with comments)
2. Explore the codebase to understand the current state
3. Break the PRD into thin vertical slices that cut through ALL layers end-to-end (schema, API, UI, tests)
4. Classify each slice as HITL (needs human) or AFK (autonomous). Prefer AFK.
5. Present the breakdown and quiz the user on granularity, dependencies, and HITL/AFK classification
6. Once approved, create GitHub issues in dependency order using `gh issue create`
7. Label all issues with `ralph` and `prd-slice` labels
8. After creating issues, generate a prd.json file in the workspace's .ralph/ directory

Each issue should use this template:
## Parent PRD
#<prd-issue-number>

## What to build
End-to-end behavior description, not layer-by-layer.

## Acceptance criteria
- [ ] Criterion 1
- [ ] Criterion 2

## Blocked by
- Blocked by #<issue> (or "None - can start immediately")

## User stories addressed
- User story N from the parent PRD

IMPORTANT: Do NOT close or modify the parent PRD issue.
SKILL;

        if ($issueNumber) {
            return $skillPrompt."\n\n---\n\nBreak down PRD issue #{$issueNumber} into vertical slice issues.";
        }

        return $skillPrompt."\n\n---\n\nAsk me for the PRD issue number to break down.";
    }

    public function getModePlaceholder(): string
    {
        return match ($this->chatMode) {
            'prd' => 'Describe the problem you want to solve...',
            'brainstorm' => 'What do you want to build or improve?',
            'debug' => 'What\'s broken? Describe the issue...',
            'code-review' => 'Which changes should I review?',
            'refactor' => 'What code needs refactoring?',
            default => 'Type a message...',
        };
    }

    /**
     * Get the system prompt for a given chat mode.
     *
     * @return string|null The mode prompt, or null for normal mode
     */
    protected function getModePrompt(string $mode): ?string
    {
        $modes = [
            'prd' => <<<'PROMPT'
You are in PRD Writing mode. Your job is to help turn an idea into a fully-formed Product Requirements Document.

## Process
1. Ask for a detailed description of the problem and any potential ideas for solutions.
2. Explore the repo to verify assertions and understand the current state of the codebase.
3. Interview the user relentlessly about every aspect until you reach a shared understanding. Ask ONE question at a time. Prefer multiple choice when possible.
4. Sketch out the major modules needed. Actively look for deep modules that encapsulate complexity behind simple interfaces.
5. Once you have complete understanding, write the PRD and submit it as a GitHub issue using `gh issue create`.

## PRD Template
Use this template for the GitHub issue body:

### Problem Statement
The problem from the user's perspective.

### Solution
The solution from the user's perspective.

### User Stories
A LONG numbered list: "As an <actor>, I want <feature>, so that <benefit>"

### Implementation Decisions
- Modules to build/modify
- Interfaces and architectural decisions
- Schema changes and API contracts

### Testing Decisions
- What makes a good test (external behavior only)
- Which modules need tests
- Prior art in the codebase

### Out of Scope
What is explicitly NOT part of this PRD.

Start by asking the user to describe the problem they want to solve.
PROMPT,

            'brainstorm' => <<<'PROMPT'
You are in Brainstorming mode. Help turn ideas into fully formed designs through natural collaborative dialogue.

## Process
- Check out the current project state first (files, docs, recent commits)
- Ask questions ONE at a time to refine the idea
- Prefer multiple choice questions when possible
- Focus on understanding: purpose, constraints, success criteria
- Propose 2-3 different approaches with trade-offs
- Lead with your recommended option and explain why
- Present the design in sections of 200-300 words, checking after each section
- Cover: architecture, components, data flow, error handling, testing
- Apply YAGNI ruthlessly - remove unnecessary features
- After design is validated, write it to docs/plans/YYYY-MM-DD-<topic>-design.md

Start by asking the user what they want to build or improve.
PROMPT,

            'debug' => <<<'PROMPT'
You are in Debug mode. Your job is to systematically diagnose and fix issues.

## Process
1. Ask the user to describe the problem (error messages, unexpected behavior, reproduction steps)
2. Check relevant logs: Laravel logs (storage/logs/), Sentry errors, browser console
3. Reproduce the issue if possible
4. Trace the code path from the symptom to the root cause
5. Check recent git changes that might have introduced the issue: `git log --oneline -20`
6. Run existing tests to see what's failing: `php artisan test`
7. Propose a fix and verify it resolves the issue
8. Add a regression test if the bug isn't covered

## Debugging Tools Available
- `php artisan test` - Run Pest test suite
- `vendor/bin/pint --test` - Check code style issues
- Laravel Telescope at /telescope - Recent requests, queries, jobs
- Sentry integration for error tracking
- Browser automation via Playwright MCP

## Key Principles
- Don't guess - trace the actual code path
- Check the database state if relevant
- Look at recent commits for regressions
- Fix the root cause, not just the symptom

Start by asking the user what's broken.
PROMPT,

            'code-review' => <<<'PROMPT'
You are in Code Review mode. Review recent changes and suggest improvements.

## Process
1. Check what's changed: `git diff`, `git log --oneline -10`, `git status`
2. Review each changed file for:
   - Logic errors and edge cases
   - Security vulnerabilities (SQL injection, XSS, CSRF)
   - N+1 query problems
   - Missing validation or authorization
   - Missing or inadequate tests
   - Code style and Laravel conventions
3. Check that new code follows existing patterns (check sibling files)
4. Run `vendor/bin/pint --dirty` to check style
5. Run `php artisan test` to verify tests pass
6. Provide feedback organized by severity: critical > important > minor > nit

## Focus Areas
- Does the code do what it claims?
- Are there missing edge cases?
- Is the code maintainable and readable?
- Are there performance concerns?
- Is the test coverage adequate?

Start by asking which changes to review (branch, PR, or recent commits).
PROMPT,

            'refactor' => <<<'PROMPT'
You are in Refactor mode. Systematically improve code quality through tiny, safe steps.

## Process
1. Ask what code needs refactoring and why
2. Explore the code and understand current structure
3. Interview about what should change and what should stay the same
4. Check test coverage - if insufficient, write tests FIRST
5. Present alternative approaches with trade-offs
6. Break the refactor into tiny commits, each leaving the codebase in a working state
7. After each step: run `vendor/bin/pint --dirty` and `php artisan test`

## Key Principles (Martin Fowler)
- Make each refactoring step as small as possible
- Never refactor and change behavior in the same commit
- Ensure tests pass after every single step
- If tests don't exist, write them before refactoring
- Prefer many tiny commits over few large ones

## Common Refactoring Patterns
- Extract Method / Extract Class
- Replace conditional with polymorphism
- Introduce Form Request for inline validation
- Replace raw queries with Eloquent relationships
- Extract Filament Actions into reusable classes

Start by asking what code the user wants to refactor and what's bothering them about it.
PROMPT,
        ];

        return $modes[$mode] ?? null;
    }

    public function copyEnvConfig(?int $configId = null): void
    {
        if (! $this->task->workspace_path || ! is_dir($this->task->workspace_path)) {
            $this->dispatch('notify', [
                'message' => 'Workspace does not exist.',
                'type' => 'error',
            ]);

            return;
        }

        $config = $configId
            ? $this->task->repository->envConfigs()->find($configId)
            : $this->defaultEnvConfig;

        if (! $config) {
            $this->dispatch('notify', [
                'message' => 'No .env config found.',
                'type' => 'error',
            ]);

            return;
        }

        $envPath = $this->task->workspace_path.'/.env';
        file_put_contents($envPath, $config->content);

        $this->dispatch('notify', [
            'message' => "Copied '{$config->name}' .env to workspace.",
        ]);
    }

    public function deleteWorkspace(): void
    {
        if (! $this->task->workspace_path) {
            return;
        }

        if (File::isDirectory($this->task->workspace_path)) {
            File::deleteDirectory($this->task->workspace_path);
        }

        $this->task->update(['workspace_path' => null]);

        $this->dispatch('workspace-deleted');
    }

    public function deleteTask(): void
    {
        // Record analytics before cascade-delete wipes messages
        \App\Models\AnalyticsEvent::recordTaskDeletion($this->task, auth()->id());

        // Delete the task (workspace directory and messages are deleted via model events/cascades)
        $this->task->delete();

        // Redirect to the tasks list
        $this->redirect(route('workbench.home'));
    }

    public function openDeployModal(): void
    {
        $this->showDeployModal = true;
        $this->deploySubdomain = '';
    }

    public function closeDeployModal(): void
    {
        $this->showDeployModal = false;
    }

    public function deployToSite(): void
    {
        $this->validate([
            'deploySubdomain' => 'required|string|min:1|max:63|regex:/^[a-z0-9-]+$/',
        ]);

        \App\Jobs\DeployToSiteJob::dispatch(
            $this->task,
            $this->deploySubdomain,
            $this->deployPhpVersion,
            $this->deployWebDirectory,
            $this->deployDatabaseName,
        );

        $this->showDeployModal = false;

        $this->dispatch('notify', [
            'message' => "Deploying to {$this->deploySubdomain}.marin.sh...",
        ]);
    }

    public function sendCompactCommand(): void
    {
        // Directly send /compact command
        $this->prompt = '/compact';
        $this->sendMessage();
    }

    public function deleteQueuedMessage(int $messageId): void
    {
        $message = Message::where('id', $messageId)
            ->where('task_id', $this->task->id)
            ->where('status', MessageStatus::Queued)
            ->first();

        if ($message) {
            $message->delete();
        }
    }

    public function generateTitle(bool $notify = true): void
    {
        $messages = $this->task->messages()->oldest()->take(20)->get();

        if ($messages->isEmpty()) {
            if ($notify) {
                Notification::make()
                    ->title('No messages to generate title from')
                    ->warning()
                    ->send();
            }

            return;
        }

        // Gather context from the first few messages (skip empty ones)
        $context = $messages->take(6)
            ->filter(fn ($m) => ! empty(trim($m->content ?? '')))
            ->map(function ($m) {
                $role = $m->role === MessageRole::User ? 'User' : 'Assistant';
                $content = $m->content ?? '';

                // Strip mode system prompt prefix (everything before the last "---" separator)
                if ($role === 'User' && str_contains($content, "\n---\n")) {
                    $parts = explode("\n---\n", $content);
                    $content = trim(end($parts));
                }

                return "{$role}: ".mb_substr($content, 0, 500);
            })->implode("\n\n");

        $prompt = "Generate a specific, descriptive 3-7 word title for this conversation. Focus on what the USER is actually asking for — the specific feature, bug, or topic. Ignore any system prompts, mode instructions, or process descriptions. Reply with ONLY the title, nothing else. No quotes, no explanation, no punctuation at the end.\n\nConversation:\n{$context}\n\nTitle:";

        try {
            // Use Kimi for fast, cheap title generation
            $kimiProvider = AiProvider::where('name', 'kimi')->where('is_active', true)->first();

            if (! $kimiProvider) {
                if ($notify) {
                    Notification::make()
                        ->title('Kimi provider not available')
                        ->body('Please configure Kimi in AI Provider Settings')
                        ->danger()
                        ->send();
                }

                return;
            }

            $client = \Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => 'Bearer '.$kimiProvider->api_key,
                'Content-Type' => 'application/json',
            ])->baseUrl(rtrim($kimiProvider->base_url, '/'));

            $response = $client->post('/v1/messages', [
                'model' => $kimiProvider->model ?? 'kimi-k2.5',
                'max_tokens' => 50,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

            if ($response->successful()) {
                $output = $response->json('content.0.text') ?? '';

                if (! empty($output)) {
                    $title = trim($output, " \n\r\t\v\0\"'");
                    // Take only the first line in case Kimi added extra content
                    $title = strtok($title, "\n");
                    // Remove any trailing punctuation
                    $title = rtrim($title, '.!?:');
                    // Limit to 50 chars max
                    if (strlen($title) > 50) {
                        $title = substr($title, 0, 50);
                    }

                    $this->task->update(['title' => $title]);
                    $this->task->refresh();

                    if ($notify) {
                        Notification::make()
                            ->title('Title updated')
                            ->body($title)
                            ->success()
                            ->send();
                    }
                } else {
                    if ($notify) {
                        Notification::make()
                            ->title('Failed to generate title')
                            ->body('Kimi returned an empty response')
                            ->danger()
                            ->send();
                    }
                }
            } else {
                if ($notify) {
                    Notification::make()
                        ->title('Failed to generate title')
                        ->body('Kimi API error: '.$response->status())
                        ->danger()
                        ->send();
                }
            }
        } catch (\Exception $e) {
            if ($notify) {
                Notification::make()
                    ->title('Error generating title')
                    ->body($e->getMessage())
                    ->danger()
                    ->send();
            }
        }
    }

    /**
     * Get an existing response to an AskUserQuestion tool call.
     *
     * @return array<string, string>|null
     */
    public function getQuestionResponse(int $messageId, string $toolId): ?array
    {
        // Check if there's a stored response in the task's metadata
        $responses = $this->task->question_responses ?? [];

        return $responses["{$messageId}_{$toolId}"] ?? null;
    }

    /**
     * Stop a running AI process.
     * This kills the subprocess and marks the task as completed.
     */
    public function stopRunning(): void
    {
        $shouldStopRalph = (bool) $this->task->ralph_enabled;
        $sessionMetadata = $this->task->session_metadata ?? [];
        $ralphProcessPid = data_get($sessionMetadata, 'ralph_process.pid');

        if (! $this->task->isRunning() && ! $this->task->has_active_subagents && ! $shouldStopRalph) {
            return;
        }

        $sessionId = $this->task->session_id;
        $provider = $this->task->aiProvider;

        if ($shouldStopRalph && is_numeric($ralphProcessPid)) {
            $this->terminateProcessTree((int) $ralphProcessPid);
        } else {
            if ($provider?->isCodex()) {
                $this->runStopCommand("pkill -f 'codex.*exec resume.*{$sessionId}' 2>/dev/null || true");
            } else {
                // Kill any Claude processes with this session ID
                $this->runStopCommand("pkill -f 'claude.*--session-id {$sessionId}' 2>/dev/null || true");
                $this->runStopCommand("pkill -f 'claude.*--resume {$sessionId}' 2>/dev/null || true");
            }

            // Also kill any AI processes associated with this task's working directory
            if ($this->task->working_directory) {
                $workingDir = escapeshellarg($this->task->working_directory);
                $pattern = $provider?->isCodex() ? 'codex.*--cd' : 'claude.*';
                $this->runStopCommand("pkill -f '{$pattern}.*{$workingDir}' 2>/dev/null || true");
            }
        }

        // Clear loop flags before marking complete so Ralph cannot keep dispatching.
        $updates = ['has_active_subagents' => false];
        if ($shouldStopRalph) {
            $updates['ralph_enabled'] = false;
            $updates['ralph_stopped_reason'] = 'stopped_by_user';
        }
        if (array_key_exists('ralph_process', $sessionMetadata)) {
            unset($sessionMetadata['ralph_process']);
            $updates['session_metadata'] = $sessionMetadata;
        }

        $this->task->update($updates);
        $this->task->markAsCompleted();

        // Clear waiting state
        $this->waitingForResponse = false;

        Log::info('AI process stopped by user', [
            'task_id' => $this->task->id,
            'session_id' => $sessionId,
        ]);

        // Notify the user
        Notification::make()
            ->title("{$this->providerLabel} stopped")
            ->body('You can send a new message now.')
            ->info()
            ->send();
    }

    protected function terminateProcessTree(int $pid): void
    {
        $this->runStopCommand("pkill -TERM -P {$pid} 2>/dev/null || true");
        $this->runStopCommand("kill -TERM {$pid} 2>/dev/null || true");
    }

    protected function runStopCommand(string $command): void
    {
        Process::run(['bash', '-lc', $command]);
    }

    /**
     * Submit a response to an AskUserQuestion tool call.
     *
     * @param  array<string, string>  $responses
     */
    public function submitQuestionResponse(int $messageId, string $toolId, array $responses): void
    {
        // Store the response in the task's metadata
        $questionResponses = $this->task->question_responses ?? [];
        $questionResponses["{$messageId}_{$toolId}"] = $responses;
        $this->task->update(['question_responses' => $questionResponses]);

        // Get the message and find the AskUserQuestion tool call
        $message = Message::where('id', $messageId)
            ->where('task_id', $this->task->id)
            ->first();

        if (! $message) {
            return;
        }

        // Find the original question to get question text for each response
        $contentBlocks = $message->content_blocks ?? [];
        $questions = [];
        foreach ($contentBlocks as $block) {
            if (($block['type'] ?? '') === 'tool_use' && ($block['tool']['id'] ?? '') === $toolId) {
                $questions = $block['tool']['input']['questions'] ?? [];
                break;
            }
        }

        // Format the response as a user message
        // The response should be structured as answers to each question
        $responseText = '';
        foreach ($responses as $index => $answer) {
            $questionText = $questions[$index]['question'] ?? "Question {$index}";
            $header = $questions[$index]['header'] ?? '';
            if ($header) {
                $responseText .= "**{$header}**: {$answer}\n";
            } else {
                $responseText .= "{$answer}\n";
            }
        }

        // Create a user message with the response
        $userMessage = Message::create([
            'task_id' => $this->task->id,
            'role' => MessageRole::User,
            'status' => MessageStatus::Sent,
            'content' => trim($responseText),
        ]);

        // Dispatch job to continue the conversation with the response
        $this->task->dispatchMessage($userMessage, continue: true);

        $this->waitingForResponse = true;
    }

    /**
     * Handle a broadcast update from WebSocket (TaskChatUpdated / TaskStatusUpdated).
     *
     * @param  array<string, mixed>  $data
     */
    public function handleBroadcastUpdate(array $data = []): void
    {
        $previousSentMessageCount = $this->messagesLoaded ? $this->sentMessageCount() : 0;

        $this->task->refresh();

        $this->expandVisibleWindowIfFullyLoaded($previousSentMessageCount);

        unset($this->chatMessages, $this->totalMessageCount, $this->hasHiddenMessages);

        $type = $data['type'] ?? null;

        if (in_array($type, ['completed', 'failed'])) {
            $this->waitingForResponse = false;
        }
    }

    public function render()
    {
        return view('livewire.task-chat');
    }

    protected function sentMessagesQuery()
    {
        return $this->task->messages()
            ->where('status', MessageStatus::Sent);
    }

    protected function sentMessageCount(): int
    {
        return $this->sentMessagesQuery()->count();
    }

    protected function expandVisibleWindowIfFullyLoaded(int $previousSentMessageCount): void
    {
        if (! $this->messagesLoaded || $previousSentMessageCount === 0) {
            return;
        }

        if ($this->visibleMessageCount < $previousSentMessageCount) {
            return;
        }

        $this->visibleMessageCount = $this->sentMessageCount();
    }
}
