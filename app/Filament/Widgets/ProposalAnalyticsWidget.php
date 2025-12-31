<?php

namespace App\Filament\Widgets;

use App\Services\ProposalAnalyticsService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ProposalAnalyticsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $service = app(ProposalAnalyticsService::class);
        $metrics = $service->getOverallMetrics();

        return [
            Stat::make('Approval Rate', $metrics['approval_rate'].'%')
                ->description($metrics['approved'].' approved / '.($metrics['approved'] + $metrics['rejected']).' decided')
                ->color($metrics['approval_rate'] >= 70 ? 'success' : ($metrics['approval_rate'] >= 50 ? 'warning' : 'danger'))
                ->icon('heroicon-o-check-circle'),

            Stat::make('Execution Success', $metrics['execution_success_rate'].'%')
                ->description($metrics['successful'].' / '.$metrics['executed'].' executed')
                ->color($metrics['execution_success_rate'] >= 80 ? 'success' : 'warning')
                ->icon('heroicon-o-bolt'),

            Stat::make('Avg Decision Time', $metrics['avg_decision_time_hours'] ? $metrics['avg_decision_time_hours'].'h' : 'N/A')
                ->description('Time from creation to decision')
                ->color($metrics['avg_decision_time_hours'] && $metrics['avg_decision_time_hours'] <= 4 ? 'success' : 'warning')
                ->icon('heroicon-o-clock'),

            Stat::make('Pending', (string) $metrics['pending'])
                ->description('Awaiting decision')
                ->color($metrics['pending'] > 10 ? 'danger' : ($metrics['pending'] > 5 ? 'warning' : 'success'))
                ->icon('heroicon-o-inbox'),
        ];
    }
}
