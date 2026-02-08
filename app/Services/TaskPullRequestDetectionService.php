<?php

namespace App\Services;

use App\Models\Task;
use Illuminate\Support\Str;

class TaskPullRequestDetectionService
{
    public function detectAndStore(Task $task): void
    {
        // Don't overwrite an existing monitor unless it's explicitly inactive.
        $existing = $task->session_metadata['pr_monitor'] ?? null;
        if (is_array($existing) && ($existing['active'] ?? false)) {
            return;
        }

        $repo = $task->repository;
        if (! $repo || ! $repo->full_name) {
            return;
        }

        $match = $this->findPullRequestUrlInRecentMessages($task);
        if (! $match) {
            return;
        }

        [$fullName, $number] = $match;

        $metadata = $task->session_metadata ?? [];
        $metadata['pr_monitor'] = [
            'active' => true,
            'repository_full_name' => $fullName,
            'pr_number' => $number,
            'detected_via' => 'message_url',
            'detected_at' => now()->toIso8601String(),
            'last_notified_sha' => null,
        ];

        $task->update(['session_metadata' => $metadata]);
    }

    /**
     * @return array{0: string, 1: int}|null
     */
    private function findPullRequestUrlInRecentMessages(Task $task): ?array
    {
        $messages = $task->messages()
            ->latest()
            ->limit(30)
            ->get();

        foreach ($messages as $message) {
            $content = (string) ($message->content ?? '');
            if ($content === '') {
                continue;
            }

            // Match https://github.com/<owner>/<repo>/pull/<number>
            if (preg_match('~https?://github\\.com/([^\\s/]+/[^\\s/]+)/pull/(\\d+)~i', $content, $m) !== 1) {
                continue;
            }

            $fullName = Str::of($m[1])->trim()->toString();
            $number = (int) $m[2];

            if ($fullName !== '' && $number > 0) {
                return [$fullName, $number];
            }
        }

        return null;
    }
}
