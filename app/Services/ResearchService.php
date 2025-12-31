<?php

namespace App\Services;

use App\Enums\ResearchModule;
use App\Models\AiProvider;
use App\Models\ResearchReport;
use App\Models\Task;
use Illuminate\Support\Facades\File;

class ResearchService
{
    public function getPromptForModule(ResearchModule $module): string
    {
        $promptPath = resource_path("prompts/research/{$module->value}.md");

        if (! File::exists($promptPath)) {
            throw new \RuntimeException("Prompt not found for module: {$module->value}");
        }

        $prompt = File::get($promptPath);

        return $this->injectPromptData($module, $prompt);
    }

    protected function injectPromptData(ResearchModule $module, string $prompt): string
    {
        $prompt = match ($module) {
            ResearchModule::ApiOpportunities => $this->injectApiData($prompt),
            ResearchModule::PromotionFinder => $this->injectDirectoryData($prompt),
            default => $prompt,
        };

        // Always inject analytics priorities
        return $this->injectAnalyticsPriorities($prompt);
    }

    protected function injectApiData(string $prompt): string
    {
        $currentApis = implode("\n", [
            '- Google Maps (search, reviews, details)',
            '- YouTube (videos, channels, comments)',
            '- Amazon (search, products, reviews)',
            '- LinkedIn (profiles, companies)',
            '- Trustpilot (reviews)',
            '- Kununu (reviews)',
            '- Indeed Jobs',
            '- Google Flights',
            '- Vinted',
            '- And more...',
        ]);

        return str_replace('{{CURRENT_APIS}}', $currentApis, $prompt);
    }

    protected function injectDirectoryData(string $prompt): string
    {
        return $prompt;
    }

    protected function injectAnalyticsPriorities(string $prompt): string
    {
        $analytics = app(\App\Services\ProposalAnalyticsService::class);
        $priorities = $analytics->getResearchPriorities();

        $priorityText = '';

        if (! empty($priorities['preferred_types'])) {
            $types = implode(', ', $priorities['preferred_types']);
            $priorityText .= "\n\n**User Preferences (from approval history):**\n";
            $priorityText .= "- Preferred proposal types: {$types}\n";
        }

        if (! empty($priorities['preferred_projects'])) {
            $projects = implode(', ', $priorities['preferred_projects']);
            $priorityText .= "- Preferred projects: {$projects}\n";
        }

        if (! empty($priorities['avoid_types'])) {
            $avoid = implode(', ', $priorities['avoid_types']);
            $priorityText .= "- Types with low approval: {$avoid} (consider avoiding)\n";
        }

        if ($priorityText) {
            $prompt .= $priorityText;
        }

        return $prompt;
    }

    public function createResearchTask(ResearchModule $module): Task
    {
        $prompt = $this->getPromptForModule($module);

        // Use GLM provider for research tasks (z.ai subscription)
        $glmProvider = AiProvider::where('name', 'glm')->where('is_active', true)->first();

        $task = Task::create([
            'title' => "Research: {$module->label()}",
            'status' => \App\Enums\TaskStatus::Pending,
            'ai_provider_id' => $glmProvider?->id,
        ]);

        $task->messages()->create([
            'role' => \App\Enums\MessageRole::User,
            'content' => $prompt,
        ]);

        return $task;
    }

    public function saveReport(
        ResearchModule $module,
        string $title,
        string $summary,
        string $content,
        int $findingsCount,
        int $proposalsCreated,
        ?Task $task = null
    ): ResearchReport {
        return ResearchReport::create([
            'module' => $module,
            'title' => $title,
            'summary' => $summary,
            'content' => $content,
            'findings_count' => $findingsCount,
            'proposals_created' => $proposalsCreated,
            'task_id' => $task?->id,
        ]);
    }
}
