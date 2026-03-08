<?php

namespace App\Services;

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
