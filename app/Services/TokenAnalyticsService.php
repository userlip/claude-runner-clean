<?php

namespace App\Services;

use App\Models\AiProvider;
use App\Models\Message;
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
        $researchTasks = Task::where('title', 'like', 'Research:%')->pluck('id');
        $researchMessages = Message::whereIn('task_id', $researchTasks);

        // Proposal execution tasks (created by ProposalExecutionService)
        $proposalTasks = Task::whereHas('proposal')->pluck('id');
        $proposalMessages = Message::whereIn('task_id', $proposalTasks);

        // Manual tasks (everything else)
        $manualTaskIds = Task::whereNotIn('id', $researchTasks->merge($proposalTasks))->pluck('id');
        $manualMessages = Message::whereIn('task_id', $manualTaskIds);

        return [
            'research' => [
                'tokens' => (int) $researchMessages->sum(DB::raw('tokens_in + tokens_out')),
                'cost' => (float) $researchMessages->sum('cost_usd'),
                'task_count' => $researchTasks->count(),
            ],
            'proposal_execution' => [
                'tokens' => (int) $proposalMessages->sum(DB::raw('tokens_in + tokens_out')),
                'cost' => (float) $proposalMessages->sum('cost_usd'),
                'task_count' => $proposalTasks->count(),
            ],
            'manual' => [
                'tokens' => (int) $manualMessages->sum(DB::raw('tokens_in + tokens_out')),
                'cost' => (float) $manualMessages->sum('cost_usd'),
                'task_count' => $manualTaskIds->count(),
            ],
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
