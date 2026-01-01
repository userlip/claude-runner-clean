<?php

namespace App\Filament\Widgets;

use App\Services\TokenAnalyticsService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TokenUsageWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $analytics = app(TokenAnalyticsService::class);
        $metrics = $analytics->getOverallMetrics();
        $byType = $analytics->getUsageByTaskType();

        $automatedTokens = $byType['research']['tokens'] + $byType['proposal_execution']['tokens'];
        $totalTokens = $metrics['total_tokens'];
        $automationPercentage = $totalTokens > 0
            ? round(($automatedTokens / $totalTokens) * 100)
            : 0;

        return [
            Stat::make('Today\'s Tokens', $analytics->formatTokens($metrics['today_tokens']))
                ->description($analytics->formatCost($metrics['today_cost']))
                ->color('primary'),

            Stat::make('This Week', $analytics->formatTokens($metrics['week_tokens']))
                ->description($analytics->formatCost($metrics['week_cost']))
                ->color('info'),

            Stat::make('All Time', $analytics->formatTokens($metrics['total_tokens']))
                ->description($analytics->formatCost($metrics['total_cost']))
                ->color('success'),

            Stat::make('Automation %', $automationPercentage.'%')
                ->description('Research + proposals')
                ->color($automationPercentage >= 50 ? 'success' : 'warning'),
        ];
    }
}
