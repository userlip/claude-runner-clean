<?php

namespace App\Console\Commands;

use App\Services\ProposalAnalyticsService;
use Illuminate\Console\Command;

class ProposalAnalyticsCommand extends Command
{
    protected $signature = 'proposal:analytics';

    protected $description = 'Display proposal analytics and success metrics';

    public function handle(ProposalAnalyticsService $service): int
    {
        $metrics = $service->getOverallMetrics();

        $this->info('=== Proposal Analytics ===');
        $this->newLine();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Proposals', $metrics['total']],
                ['Approved', $metrics['approved']],
                ['Rejected', $metrics['rejected']],
                ['Pending', $metrics['pending']],
                ['Approval Rate', $metrics['approval_rate'].'%'],
                ['Executed', $metrics['executed']],
                ['Successful', $metrics['successful']],
                ['Execution Success Rate', $metrics['execution_success_rate'].'%'],
                ['Avg Decision Time', $metrics['avg_decision_time_hours'] ? $metrics['avg_decision_time_hours'].'h' : 'N/A'],
            ]
        );

        $this->newLine();
        $this->info('=== By Type ===');

        $byType = $service->getMetricsByType();
        if ($byType->isNotEmpty()) {
            $this->table(
                ['Type', 'Total', 'Approved', 'Approval %', 'Success %'],
                $byType->map(fn ($m) => [
                    $m['type_label'],
                    $m['total'],
                    $m['approved'],
                    $m['approval_rate'].'%',
                    $m['success_rate'].'%',
                ])->toArray()
            );
        }

        $this->newLine();
        $this->info('=== By Project ===');

        $byProject = $service->getMetricsByProject();
        if ($byProject->isNotEmpty()) {
            $this->table(
                ['Project', 'Total', 'Approved', 'Approval %', 'Avg Decision'],
                $byProject->map(fn ($m) => [
                    $m['project'],
                    $m['total'],
                    $m['approved'],
                    $m['approval_rate'].'%',
                    $m['avg_decision_time_hours'] ? $m['avg_decision_time_hours'].'h' : '-',
                ])->toArray()
            );
        }

        $this->newLine();
        $this->info('=== Research Priorities ===');

        $priorities = $service->getResearchPriorities();
        $this->line('Preferred types: '.implode(', ', $priorities['preferred_types'] ?: ['(not enough data)']));
        $this->line('Preferred projects: '.implode(', ', $priorities['preferred_projects'] ?: ['(not enough data)']));
        $this->line('Avoid types: '.implode(', ', $priorities['avoid_types'] ?: ['(none)']));

        return self::SUCCESS;
    }
}
