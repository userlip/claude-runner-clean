<?php

namespace App\Console\Commands;

use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupStaleTasks extends Command
{
    protected $signature = 'tasks:cleanup-stale {--minutes=20 : Minutes of inactivity before a task is considered stale}';

    protected $description = 'Find and fix tasks stuck in running/compacting state due to hung API connections or worker restarts.';

    public function handle(): int
    {
        $minutes = (int) $this->option('minutes');
        $activityThreshold = now()->subMinutes($minutes);

        $staleTasks = Task::query()
            ->where('status', TaskStatus::Running)
            ->where('last_message_at', '<', $activityThreshold)
            ->where(function ($query) {
                $query->where('has_active_subagents', false)
                    ->orWhereNull('has_active_subagents');
            })
            ->whereDoesntHave('messages', function ($query) use ($activityThreshold) {
                $query->where('updated_at', '>=', $activityThreshold);
            })
            ->get();

        if ($staleTasks->isEmpty()) {
            return self::SUCCESS;
        }

        foreach ($staleTasks as $task) {
            $minutesStale = (int) $task->last_message_at->diffInMinutes(now());

            Log::warning('Cleaning up stale task', [
                'task_id' => $task->id,
                'title' => $task->title,
                'minutes_stale' => $minutesStale,
                'is_compacting' => $task->is_compacting,
                'has_active_subagents' => $task->has_active_subagents,
            ]);

            $task->update([
                'is_compacting' => false,
                'has_active_subagents' => false,
            ]);

            $task->markAsCompleted();

            $this->components->warn("Cleaned up stale task #{$task->id} \"{$task->title}\" ({$minutesStale}m stale)");
        }

        $this->components->info("Cleaned up {$staleTasks->count()} stale task(s).");

        return self::SUCCESS;
    }
}
