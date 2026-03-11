<?php

namespace App\Services;

use App\Enums\ProposalPriority;
use App\Enums\ProposalStatus;
use App\Enums\ProposalType;
use App\Enums\TaskStatus;
use App\Jobs\CloneRepositoryJob;
use App\Jobs\RunPersonaSubtaskJob;
use App\Models\AiProvider;
use App\Models\Persona;
use App\Models\Proposal;
use App\Models\Task;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PersonaCycleService
{
    public function __construct(
        public PersonaStorageService $storageService,
        public TelegramService $telegramService
    ) {}

    /**
     * Build structured context from a persona's persistent state files.
     *
     * @return array{context: string|null, history: array<int, array{name: string, content: string, date: string}>, completed_plans: string[]}
     */
    public function buildStateContext(Persona $persona): array
    {
        $context = $this->storageService->readContext($persona);
        $history = $this->storageService->getHistoryFiles($persona);

        $completedPlansPath = "{$persona->getStoragePath()}/completed-plans";
        $completedPlans = [];

        if (File::isDirectory($completedPlansPath)) {
            $completedPlans = collect(File::files($completedPlansPath))
                ->sortByDesc(fn ($file) => $file->getMTime())
                ->take(5)
                ->map(fn ($file) => File::get($file->getPathname()))
                ->values()
                ->all();
        }

        return [
            'context' => $context,
            'history' => $history,
            'completed_plans' => $completedPlans,
        ];
    }

    /**
     * Build the analysis prompt combining master prompt, state context, MCP guidance, and output format.
     */
    public function buildAnalysisPrompt(Persona $persona): string
    {
        $state = $this->buildStateContext($persona);

        $prompt = "## Analysis Cycle for Persona: {$persona->name}\n\n";
        $prompt .= "You are acting as \"{$persona->name}\" — {$persona->description}\n\n";

        // Master prompt
        if ($persona->master_prompt) {
            $prompt .= "### Master Prompt\n{$persona->master_prompt}\n\n";
        }

        // Current context
        if ($state['context']) {
            $prompt .= "### Current Context\n{$state['context']}\n\n";
        }

        // Recent history (last 3 entries)
        if (! empty($state['history'])) {
            $prompt .= "### Recent History\n";
            foreach (array_slice($state['history'], 0, 3) as $entry) {
                $prompt .= "#### {$entry['name']} ({$entry['date']})\n{$entry['content']}\n\n";
            }
        }

        // Completed plans summary (last 3)
        if (! empty($state['completed_plans'])) {
            $prompt .= "### Previously Completed Plans\n";
            foreach (array_slice($state['completed_plans'], 0, 3) as $plan) {
                $prompt .= "{$plan}\n\n---\n\n";
            }
        }

        // MCP guidance
        if ($persona->mcp_guidance) {
            $prompt .= "### MCP Tools Guidance\n{$persona->mcp_guidance}\n\n";
        }

        // Output format instructions
        $prompt .= "### Output Format (CRITICAL — follow exactly)\n\n";
        $prompt .= "Your output MUST contain two sections:\n\n";
        $prompt .= "#### Section 1: Individual Action Items\n";
        $prompt .= "List 3-7 specific, independent action items. Each one becomes a separate proposal the human will approve or reject individually.\n\n";
        $prompt .= "Use this EXACT format for each item (the markers are parsed programmatically):\n\n";
        $prompt .= "```\n";
        $prompt .= "PROPOSAL_START\n";
        $prompt .= "TITLE: <short imperative action — max 80 chars, e.g. \"Add JSON-LD structured data to the pricing page\">\n";
        $prompt .= "PRIORITY: <low|medium|high|critical>\n";
        $prompt .= "DESCRIPTION: <1-2 sentences explaining WHY this matters and WHAT the expected impact is>\n";
        $prompt .= "PROPOSAL_END\n";
        $prompt .= "```\n\n";
        $prompt .= "Rules for proposals:\n";
        $prompt .= "- Each proposal should be ONE specific, actionable task (not a category or group of tasks)\n";
        $prompt .= "- Title must start with a verb: Add, Fix, Update, Create, Remove, Optimize, etc.\n";
        $prompt .= "- Description should be 1-2 sentences max — concise and to the point\n";
        $prompt .= "- Order by priority (highest first)\n\n";
        $prompt .= "#### Section 2: Detailed Report\n";
        $prompt .= "After ALL proposals, include a detailed analysis section:\n\n";
        $prompt .= "```\n";
        $prompt .= "DETAILED_REPORT_START\n";
        $prompt .= "<Full analysis with data, metrics, reasoning, and supporting evidence for your proposals>\n";
        $prompt .= "DETAILED_REPORT_END\n";
        $prompt .= "```\n\n";
        $prompt .= "### Instructions\n";
        $prompt .= "1. Analyze the current state using available MCP tools and context\n";
        $prompt .= "2. Identify improvements, issues, or opportunities\n";
        $prompt .= "3. Create individual, specific proposals — NOT broad categories\n";
        $prompt .= "4. Be specific with data and examples in the detailed report\n";
        $prompt .= "5. Consider what has already been completed to avoid repeating work\n";

        return $prompt;
    }

    /**
     * Parse Claude output into individual proposals and a detailed report.
     *
     * @return array{proposals: array<int, array{title: string, priority: string, description: string}>, data_appendix: string}
     */
    public function parseAnalysisOutput(string $output): array
    {
        $proposals = [];
        $dataAppendix = '';

        // Extract individual proposals
        if (preg_match_all('/PROPOSAL_START\s*\n(.*?)PROPOSAL_END/s', $output, $matches)) {
            foreach ($matches[1] as $block) {
                $title = '';
                $priority = 'medium';
                $description = '';

                if (preg_match('/TITLE:\s*(.+)/i', $block, $m)) {
                    $title = trim($m[1]);
                }
                if (preg_match('/PRIORITY:\s*(.+)/i', $block, $m)) {
                    $priority = strtolower(trim($m[1]));
                }
                if (preg_match('/DESCRIPTION:\s*(.+)/is', $block, $m)) {
                    // Only grab up to the next field marker or end
                    $desc = trim($m[1]);
                    // Remove anything after another TITLE/PRIORITY line if accidentally captured
                    $desc = preg_replace('/\n(TITLE|PRIORITY):.*/s', '', $desc);
                    $description = trim($desc);
                }

                if ($title) {
                    $proposals[] = [
                        'title' => Str::limit($title, 120),
                        'priority' => in_array($priority, ['low', 'medium', 'high', 'critical']) ? $priority : 'medium',
                        'description' => $description,
                    ];
                }
            }
        }

        // Extract detailed report
        if (preg_match('/DETAILED_REPORT_START\s*\n(.*?)DETAILED_REPORT_END/s', $output, $matches)) {
            $dataAppendix = trim($matches[1]);
        }

        // Fallback: if no proposals were parsed, create one from the full output
        if (empty($proposals)) {
            $summaryText = $dataAppendix ?: $output;
            $proposals[] = [
                'title' => 'Analysis cycle completed — review findings',
                'priority' => 'medium',
                'description' => Str::limit(trim($summaryText), 300),
            ];
        }

        return [
            'proposals' => $proposals,
            'data_appendix' => $dataAppendix,
        ];
    }

    /**
     * Create multiple proposals from analysis results.
     *
     * @param  array<int, array{title: string, priority: string, description: string}>  $proposalItems
     * @return array<int, Proposal>
     */
    public function createProposalsFromAnalysis(Persona $persona, array $proposalItems, string $dataAppendix): array
    {
        $cycleNumber = ($persona->total_runs ?? 0) + 1;
        $project = $persona->repository?->name ?? 'unknown';
        $proposals = [];

        foreach ($proposalItems as $index => $item) {
            $proposal = Proposal::create([
                'title' => $item['title'],
                'description' => $item['description'],
                'data_appendix' => $index === 0 ? $dataAppendix : null,
                'persona_id' => $persona->id,
                'type' => ProposalType::SeoImprovement,
                'priority' => ProposalPriority::tryFrom($item['priority']) ?? ProposalPriority::Medium,
                'status' => ProposalStatus::Pending,
                'project' => $project,
            ]);

            $proposals[] = $proposal;
        }

        return $proposals;
    }

    /**
     * Log a cycle run to storage/personas/{slug}/history/{date}-cycle-{n}.md
     */
    public function logCycleToHistory(Persona $persona, Proposal $proposal, int $cycleNumber): void
    {
        $date = now()->format('Y-m-d');
        $filename = "{$date}-cycle-{$cycleNumber}.md";
        $path = "{$persona->getStoragePath()}/history/{$filename}";

        File::ensureDirectoryExists(dirname($path));

        $content = <<<MARKDOWN
        # Cycle #{$cycleNumber} — {$date}

        **Persona:** {$persona->name}
        **Proposal:** {$proposal->title}
        **Status:** Pending Approval

        ## Executive Summary
        {$proposal->description}

        ## Data Appendix
        {$proposal->data_appendix}
        MARKDOWN;

        File::put($path, $content);
    }

    /**
     * Start executing approved subtasks for a persona proposal.
     * Creates a Task, clones repo workspace, dispatches first RunPersonaSubtaskJob.
     */
    public function startSubtaskExecution(Proposal $proposal): Task
    {
        $persona = $proposal->persona;
        $repository = $persona->repository;

        $aiProvider = $persona->aiProvider
            ?? AiProvider::where('name', 'kimi')->where('is_active', true)->first()
            ?? AiProvider::getDefault();

        $workspacePath = '/home/ploi/workspaces/'.Str::slug($repository->name).'-'.Str::random(8);

        $task = Task::create([
            'title' => "[Persona] {$proposal->title}",
            'status' => TaskStatus::Pending,
            'ai_provider_id' => $aiProvider?->id,
            'repository_id' => $repository->id,
            'workspace_path' => $workspacePath,
            'user_id' => $persona->user_id,
        ]);

        $proposal->update([
            'executed_task_id' => $task->id,
            'current_subtask_index' => 0,
        ]);

        $proposal->updateSubtaskStatus(0, 'running');

        Log::info('Starting persona subtask execution', [
            'proposal_id' => $proposal->id,
            'persona' => $persona->name,
            'task_id' => $task->id,
            'subtask_count' => count($proposal->subtasks),
        ]);

        $subtaskJob = new RunPersonaSubtaskJob($proposal->fresh(), $task);

        CloneRepositoryJob::withChain([$subtaskJob])->dispatch($task);

        return $task;
    }

    /**
     * Called when all subtasks are completed.
     * Logs to completed-plans, updates context.md, marks proposal complete, sends notification.
     */
    public function completeExecution(Proposal $proposal): void
    {
        $persona = $proposal->persona;
        $proposal->markExecutionComplete(true);

        $this->logCompletedPlan($persona, $proposal);
        $this->updateContextWithLearnings($persona, $proposal);

        $persona->update([
            'status' => \App\Enums\PersonaStatus::Active,
        ]);

        Log::info('Persona subtask execution completed', [
            'proposal_id' => $proposal->id,
            'persona' => $persona->name,
        ]);

        $this->sendCompletionNotification($persona, $proposal);
    }

    /**
     * Log completed plan to storage/personas/{slug}/completed-plans/{date}-{slug}.md
     */
    protected function logCompletedPlan(Persona $persona, Proposal $proposal): void
    {
        $date = now()->format('Y-m-d');
        $slug = Str::slug($proposal->title);
        $filename = "{$date}-{$slug}.md";
        $path = "{$persona->getStoragePath()}/completed-plans/{$filename}";

        File::ensureDirectoryExists(dirname($path));

        $subtaskSummary = collect($proposal->subtasks)
            ->map(fn (array $s, int $i) => sprintf('%d. **%s** — %s', $i + 1, $s['title'], $s['status']))
            ->implode("\n");

        $content = <<<MARKDOWN
        # {$proposal->title}

        **Date:** {$date}
        **Persona:** {$persona->name}
        **Status:** Completed

        ## Summary
        {$proposal->description}

        ## Subtasks
        {$subtaskSummary}
        MARKDOWN;

        File::put($path, $content);
    }

    /**
     * Append key learnings to persona context.md after completion.
     */
    protected function updateContextWithLearnings(Persona $persona, Proposal $proposal): void
    {
        $currentContext = $this->storageService->readContext($persona) ?? '';

        $date = now()->format('Y-m-d');
        $subtaskTitles = collect($proposal->subtasks)
            ->map(fn (array $s) => "- {$s['title']} ({$s['status']})")
            ->implode("\n");

        $learningEntry = <<<MARKDOWN


        ## Completed: {$proposal->title} ({$date})
        {$subtaskTitles}
        MARKDOWN;

        $this->storageService->writeContext($persona, $currentContext.$learningEntry);
    }

    /**
     * Send Telegram notification when all subtasks complete.
     */
    protected function sendCompletionNotification(Persona $persona, Proposal $proposal): void
    {
        $subtaskCount = count($proposal->subtasks ?? []);

        $text = "✅ *Persona Execution Complete*\n\n";
        $text .= "*Persona:* {$persona->name}\n";
        $text .= "*Proposal:* {$proposal->title}\n";
        $text .= "*Subtasks:* {$subtaskCount} completed\n";

        $this->telegramService->sendPlainMessage($text);
    }
}
