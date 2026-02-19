<?php

namespace App\Services;

use App\Enums\ProposalType;
use App\Jobs\CloneRepositoryJob;
use App\Jobs\RunClaudeMessageJob;
use App\Jobs\RunCodexMessageJob;
use App\Models\AiProvider;
use App\Models\Playbook;
use App\Models\Proposal;
use App\Models\Repository;
use App\Models\Task;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ProposalExecutionService
{
    public function execute(Proposal $proposal): Task
    {
        // Find repository by project key
        $repository = Repository::findByProjectKey($proposal->project);

        // Use Kimi provider for autonomous tasks
        $kimiProvider = AiProvider::where('name', 'kimi')->where('is_active', true)->first();

        // Create workspace path if repository exists
        $workspacePath = null;
        if ($repository) {
            $workspacePath = '/home/ploi/workspaces/'.Str::slug($repository->name).'-'.Str::random(8);
        }

        // Create the task
        $task = Task::create([
            'title' => $proposal->title,
            'status' => \App\Enums\TaskStatus::Pending,
            'ai_provider_id' => $kimiProvider?->id ?? AiProvider::getDefault()?->id,
            'repository_id' => $repository?->id,
            'workspace_path' => $workspacePath,
        ]);

        // Generate the prompt with skill instructions
        $prompt = $this->generatePrompt($proposal);

        // Create the initial message
        $message = $task->messages()->create([
            'role' => \App\Enums\MessageRole::User,
            'content' => $prompt,
        ]);

        // Link the task to the proposal
        $proposal->update(['executed_task_id' => $task->id]);

        // Send Telegram notification
        $this->sendStartNotification($proposal, $task);

        // If repository exists, clone it first then run Claude
        if ($repository && $workspacePath) {
            $runnerJob = $task->aiProvider?->isCodex()
                ? new RunCodexMessageJob($task, $message)
                : new RunClaudeMessageJob($task, $message);

            CloneRepositoryJob::withChain([$runnerJob])->dispatch($task);
        } else {
            $task->dispatchMessage($message);
        }

        return $task;
    }

    protected function generatePrompt(Proposal $proposal): string
    {
        $type = $proposal->type ?? ProposalType::Other;

        // Try to find a matching playbook
        $playbook = Playbook::findBestMatch($type, $proposal->project);

        // If playbook exists, use its template
        if ($playbook) {
            $proposal->update(['playbook_id' => $playbook->id]);

            return $playbook->buildPrompt($proposal);
        }

        // Fall back to default prompt generation
        $skills = $type->getRequiredSkills();
        $proposedAction = $proposal->proposed_action ?? [];

        $prompt = "## Task: {$proposal->title}\n\n";

        // Add description
        if ($proposal->description) {
            $prompt .= "### Description\n{$proposal->description}\n\n";
        }

        // Add required skills section
        if (! empty($skills)) {
            $prompt .= "### Required Skills (INVOKE THESE)\n";
            $prompt .= "You MUST use these skills in order. Do not skip any skill.\n\n";
            foreach ($skills as $index => $skill) {
                $num = $index + 1;
                $prompt .= "{$num}. `/{$skill}`\n";
            }
            $prompt .= "\n";
        }

        // Add context from proposed_action
        if (! empty($proposedAction)) {
            $prompt .= "### Context\n";

            if (isset($proposedAction['target'])) {
                $prompt .= "- **Target:** {$proposedAction['target']}\n";
            }

            if (isset($proposedAction['endpoint'])) {
                $prompt .= "- **Endpoint:** {$proposedAction['endpoint']}\n";
            }

            if (isset($proposedAction['service'])) {
                $prompt .= "- **Service:** {$proposedAction['service']}\n";
            }

            if (isset($proposedAction['files_to_modify']) && is_array($proposedAction['files_to_modify'])) {
                $files = implode(', ', $proposedAction['files_to_modify']);
                $prompt .= "- **Files to modify:** {$files}\n";
            }

            if (isset($proposedAction['instructions'])) {
                $prompt .= "\n### Instructions\n{$proposedAction['instructions']}\n";
            }

            $prompt .= "\n";
        }

        // Add project context
        $prompt .= "### Project\n";
        $prompt .= "- **Project:** {$proposal->project}\n";
        $prompt .= "- **Priority:** {$proposal->priority->value}\n";
        $prompt .= "\n";

        // Add completion requirements
        $prompt .= "### Completion Requirements\n";
        $prompt .= "1. Follow all skills in order - do not skip any\n";
        $prompt .= "2. Create Proposals for any follow-up work discovered\n";
        $prompt .= "3. Commit your changes with descriptive messages\n";
        $prompt .= "4. Report completion status when done\n";

        return $prompt;
    }

    protected function sendStartNotification(Proposal $proposal, Task $task): void
    {
        $token = config('services.telegram.bot_token');
        $chatId = config('services.telegram.chat_id');

        if (! $token || ! $chatId) {
            return;
        }

        $typeLabel = $proposal->type?->label() ?? 'Other';

        $message = "🚀 *Task Started*\n\n";
        $message .= "*Proposal:* {$proposal->title}\n";
        $message .= "*Project:* {$proposal->project}\n";
        $message .= "*Type:* {$typeLabel}\n\n";
        $message .= "Task #{$task->id} is now executing autonomously.";

        Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'Markdown',
        ]);
    }
}
