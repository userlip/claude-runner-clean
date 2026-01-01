<?php

namespace App\Console\Commands;

use App\Services\TokenAnalyticsService;
use Illuminate\Console\Command;

class TokenAnalyticsCommand extends Command
{
    protected $signature = 'token:analytics';

    protected $description = 'Display token usage analytics and costs';

    public function handle(TokenAnalyticsService $analytics): int
    {
        $this->info('=== Token Usage Analytics ===');
        $this->newLine();

        // Overall metrics
        $metrics = $analytics->getOverallMetrics();

        $this->table(
            ['Period', 'Tokens In', 'Tokens Out', 'Total', 'Cost'],
            [
                [
                    'Today',
                    $analytics->formatTokens($metrics['today_tokens_in']),
                    $analytics->formatTokens($metrics['today_tokens_out']),
                    $analytics->formatTokens($metrics['today_tokens']),
                    $analytics->formatCost($metrics['today_cost']),
                ],
                [
                    'This Week',
                    $analytics->formatTokens($metrics['week_tokens_in']),
                    $analytics->formatTokens($metrics['week_tokens_out']),
                    $analytics->formatTokens($metrics['week_tokens']),
                    $analytics->formatCost($metrics['week_cost']),
                ],
                [
                    'All Time',
                    $analytics->formatTokens($metrics['total_tokens_in']),
                    $analytics->formatTokens($metrics['total_tokens_out']),
                    $analytics->formatTokens($metrics['total_tokens']),
                    $analytics->formatCost($metrics['total_cost']),
                ],
            ]
        );

        // By provider
        $this->newLine();
        $this->info('=== Usage by Provider ===');

        $byProvider = $analytics->getUsageByProvider();
        if ($byProvider->isNotEmpty()) {
            $this->table(
                ['Provider', 'Tokens', 'Cost', 'Tasks'],
                $byProvider->map(fn ($p) => [
                    $p->display_name ?? $p->name,
                    $analytics->formatTokens((int) $p->total_tokens),
                    $analytics->formatCost((float) $p->total_cost),
                    $p->task_count,
                ])->toArray()
            );
        } else {
            $this->warn('No provider usage data yet');
        }

        // By task type
        $this->newLine();
        $this->info('=== Usage by Type ===');

        $byType = $analytics->getUsageByTaskType();
        $this->table(
            ['Type', 'Tokens', 'Cost', 'Tasks'],
            [
                [
                    'Research (automated)',
                    $analytics->formatTokens($byType['research']['tokens']),
                    $analytics->formatCost($byType['research']['cost']),
                    $byType['research']['task_count'],
                ],
                [
                    'Proposal Execution',
                    $analytics->formatTokens($byType['proposal_execution']['tokens']),
                    $analytics->formatCost($byType['proposal_execution']['cost']),
                    $byType['proposal_execution']['task_count'],
                ],
                [
                    'Manual',
                    $analytics->formatTokens($byType['manual']['tokens']),
                    $analytics->formatCost($byType['manual']['cost']),
                    $byType['manual']['task_count'],
                ],
            ]
        );

        // Automation percentage
        $automatedTokens = $byType['research']['tokens'] + $byType['proposal_execution']['tokens'];
        $totalTokens = $metrics['total_tokens'];
        $automationPct = $totalTokens > 0 ? round(($automatedTokens / $totalTokens) * 100) : 0;

        $this->newLine();
        $this->info("Automation: {$automationPct}% of tokens used by automated tasks");

        // Provider quotas
        $this->newLine();
        $this->info('=== Provider Quotas ===');

        $quotas = $analytics->getProviderQuotas();
        $this->table(
            ['Provider', 'Used', 'Limit', 'Usage %', 'Resets'],
            $quotas->map(fn ($q) => [
                $q['display_name'] ?? $q['name'],
                $analytics->formatTokens($q['quota_used'] ?? 0),
                $q['quota_limit'] ? $analytics->formatTokens($q['quota_limit']) : 'Unlimited',
                $q['quota_limit'] ? round($q['quota_percentage']).'%' : '-',
                $q['resets_at'] ?? '-',
            ])->toArray()
        );

        // Daily usage last 7 days
        $this->newLine();
        $this->info('=== Daily Usage (Last 7 Days) ===');

        $daily = $analytics->getDailyUsage(7);
        if ($daily->isNotEmpty()) {
            $this->table(
                ['Date', 'Tokens', 'Cost'],
                $daily->map(fn ($d) => [
                    $d->date,
                    $analytics->formatTokens((int) $d->total_tokens),
                    $analytics->formatCost((float) $d->total_cost),
                ])->toArray()
            );
        } else {
            $this->warn('No daily usage data yet');
        }

        return self::SUCCESS;
    }
}
