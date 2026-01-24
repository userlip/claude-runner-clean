<?php

namespace App\Services;

use App\Enums\MajorUpgradeStatus;
use App\Enums\MessageRole;
use App\Enums\MessageStatus;
use App\Models\MajorUpgradeRun;
use App\Models\Message;
use App\Models\Repository;
use App\Models\Task;
use Illuminate\Support\Facades\File;

class MajorUpgradeService
{
    public function dispatchOrchestratorPrompt(MajorUpgradeRun $run): void
    {
        $taskId = $run->created_by_task_id;
        if (! $taskId) {
            return;
        }

        $task = Task::findOrFail($taskId);
        $run->loadMissing('repository');

        $promptPath = resource_path('prompts/major-upgrade/orchestrator.md');
        $template = File::exists($promptPath)
            ? File::get($promptPath)
            : 'You are the major upgrade orchestrator.';

        $payload = [
            'repo' => $run->repository?->full_name,
            'pr_number' => $run->github_pr_number,
            'head_sha' => $run->source_pr_sha,
        ];

        $content = rtrim($template)."\n\n```json\n".json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n```";

        if ($task->isRunning()) {
            Message::create([
                'task_id' => $task->id,
                'role' => MessageRole::User,
                'status' => MessageStatus::Queued,
                'content' => $content,
            ]);

            $run->update(['status' => MajorUpgradeStatus::Researching]);

            return;
        }

        $message = Message::create([
            'task_id' => $task->id,
            'role' => MessageRole::User,
            'status' => MessageStatus::Sent,
            'content' => $content,
        ]);

        $task->dispatchMessage($message);
        $run->update(['status' => MajorUpgradeStatus::Researching]);
    }

    public function dispatchPendingRunsForRepository(Repository $repo): void
    {
        $runs = MajorUpgradeRun::query()
            ->where('repository_id', $repo->id)
            ->where('status', MajorUpgradeStatus::Pending)
            ->get();

        foreach ($runs as $run) {
            $this->dispatchOrchestratorPrompt($run);
        }
    }
}
