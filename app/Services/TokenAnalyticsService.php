<?php

namespace App\Services;

use App\Models\AiProvider;
use App\Models\Message;
use App\Models\Proposal;
use App\Models\Task;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TokenAnalyticsService
{
    public function getOverallMetrics(): array
    {
        $messages = Message::query();

        $totalTokensIn = $messages->sum('tokens_in');
        $totalTokensOut = $messages->sum('tokens_out');
        $totalCost = $messages->sum('cost_usd');

        $todayMessages = Message::whereDate('created_at', today());
        $todayTokensIn = $todayMessages->sum('tokens_in');
        $todayTokensOut = $todayMessages->sum('tokens_out');
        $todayCost = $todayMessages->sum('cost_usd');

        $weekMessages = Message::where('created_at', '>=', now()->subDays(7));
        $weekTokensIn = $weekMessages->sum('tokens_in');
        $weekTokensOut = $weekMessages->sum('tokens_out');
        $weekCost = $weekMessages->sum('cost_usd');

        return [
            'total_tokens_in' => (int) $totalTokensIn,
            'total_tokens_out' => (int) $totalTokensOut,
            'total_tokens' => (int) ($totalTokensIn + $totalTokensOut),
            'total_cost' => (float) $totalCost,
            'today_tokens_in' => (int) $todayTokensIn,
            'today_tokens_out' => (int) $todayTokensOut,
            'today_tokens' => (int) ($todayTokensIn + $todayTokensOut),
            'today_cost' => (float) $todayCost,
            'week_tokens_in' => (int) $weekTokensIn,
            'week_tokens_out' => (int) $weekTokensOut,
            'week_tokens' => (int) ($weekTokensIn + $weekTokensOut),
            'week_cost' => (float) $weekCost,
        ];
    }

    public function getUsageByProvider(): Collection
    {
        return Message::query()
            ->join('tasks', 'messages.task_id', '=', 'tasks.id')
            ->join('ai_providers', 'tasks.ai_provider_id', '=', 'ai_providers.id')
            ->select(
                'ai_providers.name',
                'ai_providers.display_name',
                DB::raw('SUM(messages.tokens_in) as tokens_in'),
                DB::raw('SUM(messages.tokens_out) as tokens_out'),
                DB::raw('SUM(messages.tokens_in + messages.tokens_out) as total_tokens'),
                DB::raw('SUM(messages.cost_usd) as total_cost'),
                DB::raw('COUNT(DISTINCT tasks.id) as task_count'),
            )
            ->groupBy('ai_providers.id', 'ai_providers.name', 'ai_providers.display_name')
            ->get();
    }

    public function getDailyUsage(int $days = 7): Collection
    {
        return Message::query()
            ->where('created_at', '>=', now()->subDays($days))
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(tokens_in) as tokens_in'),
                DB::raw('SUM(tokens_out) as tokens_out'),
                DB::raw('SUM(tokens_in + tokens_out) as total_tokens'),
                DB::raw('SUM(cost_usd) as total_cost'),
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();
    }

    public function getUsageByTaskType(): array
    {
        // Research tasks (created by ResearchService)
        $researchTaskIds = Task::where('title', 'like', 'Research:%')->pluck('id');

        // Proposal execution tasks (via Proposal.executed_task_id)
        $proposalTaskIds = Proposal::whereNotNull('executed_task_id')->pluck('executed_task_id');

        // Manual tasks (everything else)
        $automatedIds = $researchTaskIds->merge($proposalTaskIds);
        $manualTaskIds = Task::whereNotIn('id', $automatedIds)->pluck('id');

        return [
            'research' => $this->getTaskTypeStats($researchTaskIds),
            'proposal_execution' => $this->getTaskTypeStats($proposalTaskIds),
            'manual' => $this->getTaskTypeStats($manualTaskIds),
        ];
    }

    private function getTaskTypeStats(Collection $taskIds): array
    {
        if ($taskIds->isEmpty()) {
            return ['tokens' => 0, 'cost' => 0.0, 'task_count' => 0];
        }

        $messages = Message::whereIn('task_id', $taskIds);

        return [
            'tokens' => (int) ($messages->sum('tokens_in') + $messages->sum('tokens_out')),
            'cost' => (float) $messages->sum('cost_usd'),
            'task_count' => $taskIds->count(),
        ];
    }

    public function getProviderQuotas(): Collection
    {
        return AiProvider::where('is_active', true)
            ->get()
            ->map(function (AiProvider $provider) {
                return [
                    'name' => $provider->name,
                    'display_name' => $provider->display_name,
                    'quota_used' => $provider->quota_used,
                    'quota_limit' => $provider->quota_limit,
                    'quota_percentage' => $provider->getQuotaPercentage(),
                    'resets_at' => $provider->quota_resets_at?->diffForHumans(),
                ];
            });
    }

    public function formatTokens(int $tokens): string
    {
        if ($tokens >= 1000000) {
            return number_format($tokens / 1000000, 1).'M';
        }
        if ($tokens >= 1000) {
            return number_format($tokens / 1000, 1).'K';
        }

        return (string) $tokens;
    }

    public function formatCost(float $cost): string
    {
        return '$'.number_format($cost, 2);
    }
}
