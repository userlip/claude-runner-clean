<?php

namespace App\Jobs;

use App\Models\Task;
use App\Services\AsanaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncAsanaTaskCompletion implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    public function __construct(public Task $task) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Skip if no Asana task is linked
        if (empty($this->task->asana_task_id)) {
            return;
        }

        $user = $this->task->user;
        if (! $user) {
            Log::warning('SyncAsanaTaskCompletion: Task has no user', ['task_id' => $this->task->id]);

            return;
        }

        $connection = $user->asanaConnection;
        if (! $connection) {
            Log::warning('SyncAsanaTaskCompletion: User has no Asana connection', [
                'task_id' => $this->task->id,
                'user_id' => $user->id,
            ]);

            return;
        }

        $asanaService = new AsanaService($connection->credentials);

        // Build the completion comment
        $comment = $this->buildCompletionComment();

        // Add comment to Asana task
        $result = $asanaService->addCommentToTask($this->task->asana_task_id, $comment);
        if ($result === null) {
            Log::error('SyncAsanaTaskCompletion: Failed to add comment to Asana task', [
                'task_id' => $this->task->id,
                'asana_task_id' => $this->task->asana_task_id,
            ]);
            throw new \RuntimeException('Failed to add comment to Asana task');
        }

        // Move task to testing section if available
        $this->moveToTestingSection($asanaService);

        Log::info('SyncAsanaTaskCompletion: Successfully synced task completion to Asana', [
            'task_id' => $this->task->id,
            'asana_task_id' => $this->task->asana_task_id,
        ]);
    }

    /**
     * Build the completion comment text.
     */
    protected function buildCompletionComment(): string
    {
        $taskTitle = $this->task->title;
        $prLink = $this->extractPrLink();

        $lines = [
            '✅ **Code Review Completed**',
            '',
            "The code review task \"{$taskTitle}\" has been completed.",
        ];

        if ($prLink) {
            $lines[] = '';
            $lines[] = "**Pull Request:** {$prLink}";
        }

        $lines[] = '';
        $lines[] = '_Synced from Claude Runner_';

        return implode("\n", $lines);
    }

    /**
     * Extract PR link from task messages if available.
     */
    protected function extractPrLink(): ?string
    {
        // Check if this is a security/PR task by title
        $title = $this->task->title ?? '';

        // Match "Security PR #123" pattern
        if (preg_match('/PR\s*#?(\d+)/i', $title, $matches)) {
            $repository = $this->task->repository;
            if ($repository) {
                return "https://github.com/{$repository->full_name}/pull/{$matches[1]}";
            }
        }

        // Check messages for PR links
        $messages = $this->task->messages()->orderBy('created_at', 'asc')->get();
        foreach ($messages as $message) {
            $content = $message->content ?? '';

            // Look for GitHub PR URLs
            if (preg_match('/https:\/\/github\.com\/[^\s]+\/pull\/\d+/i', $content, $matches)) {
                return $matches[0];
            }

            // Look for PR # references
            if (preg_match('/PR\s*#?(\d+)/i', $content, $matches)) {
                $repository = $this->task->repository;
                if ($repository) {
                    return "https://github.com/{$repository->full_name}/pull/{$matches[1]}";
                }
            }
        }

        return null;
    }

    /**
     * Move the Asana task to the testing section.
     */
    protected function moveToTestingSection(AsanaService $asanaService): void
    {
        $repository = $this->task->repository;
        if (! $repository || ! $repository->asana_project_id) {
            return;
        }

        // Try configured testing section first
        $sectionId = $repository->asana_testing_section_id;

        // If no configured section, try auto-detect
        if (! $sectionId) {
            $sectionId = $asanaService->findTestingSection($repository->asana_project_id);
        }

        if (! $sectionId) {
            Log::info('SyncAsanaTaskCompletion: No testing section found', [
                'task_id' => $this->task->id,
                'repository_id' => $repository->id,
            ]);

            return;
        }

        $result = $asanaService->moveTaskToSection($this->task->asana_task_id, $sectionId);

        if ($result === null) {
            Log::warning('SyncAsanaTaskCompletion: Failed to move task to testing section', [
                'task_id' => $this->task->id,
                'asana_task_id' => $this->task->asana_task_id,
                'section_id' => $sectionId,
            ]);
        } else {
            Log::info('SyncAsanaTaskCompletion: Successfully moved task to testing section', [
                'task_id' => $this->task->id,
                'section_id' => $sectionId,
            ]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('SyncAsanaTaskCompletion: Job failed after retries', [
            'task_id' => $this->task->id,
            'asana_task_id' => $this->task->asana_task_id,
            'exception' => $exception->getMessage(),
        ]);
    }
}
