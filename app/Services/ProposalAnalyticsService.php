<?php

namespace App\Services;

use App\Enums\ProposalStatus;
use App\Enums\ProposalType;
use App\Models\Playbook;
use App\Models\Proposal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProposalAnalyticsService
{
    /**
     * @return array{
     *     total: int,
     *     approved: int,
     *     rejected: int,
     *     pending: int,
     *     approval_rate: float,
     *     executed: int,
     *     successful: int,
     *     execution_success_rate: float,
     *     avg_decision_time_hours: float
     * }
     */
    public function getOverallMetrics(): array
    {
        $total = Proposal::count();
        $approved = Proposal::where('status', ProposalStatus::Approved)->count();
        $rejected = Proposal::where('status', ProposalStatus::Rejected)->count();
        $pending = Proposal::where('status', ProposalStatus::Pending)->count();

        $decided = $approved + $rejected;
        $approvalRate = $decided > 0 ? round(($approved / $decided) * 100, 2) : 0;

        $executed = Proposal::whereNotNull('execution_completed_at')->count();
        $successful = Proposal::where('execution_success', true)->count();
        $executionSuccessRate = $executed > 0 ? round(($successful / $executed) * 100, 2) : 0;

        $avgDecisionTimeSeconds = Proposal::whereNotNull('decision_time_seconds')
            ->avg('decision_time_seconds') ?? 0;
        $avgDecisionTimeHours = round($avgDecisionTimeSeconds / 3600, 2);

        return [
            'total' => $total,
            'approved' => $approved,
            'rejected' => $rejected,
            'pending' => $pending,
            'approval_rate' => $approvalRate,
            'executed' => $executed,
            'successful' => $successful,
            'execution_success_rate' => $executionSuccessRate,
            'avg_decision_time_hours' => $avgDecisionTimeHours,
        ];
    }

    /**
     * @return Collection<int, array{
     *     type: string,
     *     type_label: string,
     *     total: int,
     *     approved: int,
     *     rejected: int,
     *     approval_rate: float,
     *     executed: int,
     *     successful: int,
     *     success_rate: float
     * }>
     */
    public function getMetricsByType(): Collection
    {
        return Proposal::query()
            ->select('type')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as approved', [ProposalStatus::Approved->value])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as rejected', [ProposalStatus::Rejected->value])
            ->selectRaw('SUM(CASE WHEN execution_completed_at IS NOT NULL THEN 1 ELSE 0 END) as executed')
            ->selectRaw('SUM(CASE WHEN execution_success = true THEN 1 ELSE 0 END) as successful')
            ->groupBy('type')
            ->get()
            ->map(function ($row) {
                $decided = $row->approved + $row->rejected;
                // Handle both string and enum values (type is cast to enum in the model)
                $typeEnum = $row->type instanceof ProposalType ? $row->type : ProposalType::tryFrom($row->type);
                $typeValue = $row->type instanceof ProposalType ? $row->type->value : $row->type;

                return [
                    'type' => $typeValue,
                    'type_label' => $typeEnum?->label() ?? $typeValue,
                    'total' => (int) $row->total,
                    'approved' => (int) $row->approved,
                    'rejected' => (int) $row->rejected,
                    'approval_rate' => $decided > 0 ? round(($row->approved / $decided) * 100, 2) : 0,
                    'executed' => (int) $row->executed,
                    'successful' => (int) $row->successful,
                    'success_rate' => $row->executed > 0 ? round(($row->successful / $row->executed) * 100, 2) : 0,
                ];
            });
    }

    /**
     * @return Collection<int, array{
     *     project: string,
     *     total: int,
     *     approved: int,
     *     rejected: int,
     *     approval_rate: float,
     *     avg_decision_time_hours: float|null
     * }>
     */
    public function getMetricsByProject(): Collection
    {
        return Proposal::query()
            ->select('project')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as approved', [ProposalStatus::Approved->value])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as rejected', [ProposalStatus::Rejected->value])
            ->selectRaw('AVG(decision_time_seconds) as avg_decision_time_seconds')
            ->whereNotNull('project')
            ->groupBy('project')
            ->get()
            ->map(function ($row) {
                $decided = $row->approved + $row->rejected;
                $avgSeconds = $row->avg_decision_time_seconds;

                return [
                    'project' => $row->project,
                    'total' => (int) $row->total,
                    'approved' => (int) $row->approved,
                    'rejected' => (int) $row->rejected,
                    'approval_rate' => $decided > 0 ? round(($row->approved / $decided) * 100, 2) : 0,
                    'avg_decision_time_hours' => $avgSeconds ? round($avgSeconds / 3600, 2) : null,
                ];
            });
    }

    /**
     * @return Collection<int, object{reason: string, count: int}>
     */
    public function getTopRejectionReasons(int $limit = 5): Collection
    {
        return Proposal::query()
            ->select('rejected_reason as reason')
            ->selectRaw('COUNT(*) as count')
            ->where('status', ProposalStatus::Rejected)
            ->whereNotNull('rejected_reason')
            ->where('rejected_reason', '!=', '')
            ->groupBy('rejected_reason')
            ->orderByDesc('count')
            ->limit($limit)
            ->get();
    }

    /**
     * @return array{
     *     days: array<string>,
     *     approved: array<int>,
     *     rejected: array<int>,
     *     total: array<int>
     * }
     */
    public function getRecentTrend(int $days = 30): array
    {
        $startDate = now()->subDays($days)->startOfDay();

        $data = Proposal::query()
            ->select(DB::raw('DATE(created_at) as date'))
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as approved', [ProposalStatus::Approved->value])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as rejected', [ProposalStatus::Rejected->value])
            ->selectRaw('COUNT(*) as total')
            ->where('created_at', '>=', $startDate)
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $daysArray = [];
        $approvedArray = [];
        $rejectedArray = [];
        $totalArray = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $daysArray[] = $date;

            $dayData = $data->get($date);
            $approvedArray[] = $dayData ? (int) $dayData->approved : 0;
            $rejectedArray[] = $dayData ? (int) $dayData->rejected : 0;
            $totalArray[] = $dayData ? (int) $dayData->total : 0;
        }

        return [
            'days' => $daysArray,
            'approved' => $approvedArray,
            'rejected' => $rejectedArray,
            'total' => $totalArray,
        ];
    }

    /**
     * @return array{
     *     preferred_types: array<string>,
     *     preferred_projects: array<string>,
     *     avoid_types: array<string>
     * }
     */
    public function getResearchPriorities(): array
    {
        $byType = $this->getMetricsByType();
        $byProject = $this->getMetricsByProject();

        $preferredTypes = $byType
            ->filter(fn ($row) => $row['approval_rate'] >= 70 && $row['total'] >= 3)
            ->sortByDesc('approval_rate')
            ->pluck('type')
            ->values()
            ->toArray();

        $avoidTypes = $byType
            ->filter(fn ($row) => $row['approval_rate'] < 30 && $row['total'] >= 3)
            ->sortBy('approval_rate')
            ->pluck('type')
            ->values()
            ->toArray();

        $preferredProjects = $byProject
            ->filter(fn ($row) => $row['approval_rate'] >= 70 && $row['total'] >= 3)
            ->sortByDesc('approval_rate')
            ->pluck('project')
            ->values()
            ->toArray();

        return [
            'preferred_types' => $preferredTypes,
            'preferred_projects' => $preferredProjects,
            'avoid_types' => $avoidTypes,
        ];
    }

    /**
     * Detect patterns in approved proposals that could become playbooks.
     *
     * @return array<int, array{
     *     type: string,
     *     type_label: string,
     *     project: string|null,
     *     count: int,
     *     success_rate: float,
     *     has_playbook: bool,
     *     suggestion: string
     * }>
     */
    public function detectPlaybookOpportunities(): array
    {
        // Find type+project combinations with 3+ successful executions but no playbook
        $patterns = Proposal::query()
            ->select('type', 'project')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN execution_success = true THEN 1 ELSE 0 END) as successful')
            ->where('status', ProposalStatus::Approved)
            ->whereNotNull('execution_completed_at')
            ->groupBy('type', 'project')
            ->having('total', '>=', 3)
            ->get();

        $opportunities = [];

        foreach ($patterns as $pattern) {
            $type = $pattern->type instanceof ProposalType ? $pattern->type : ProposalType::tryFrom($pattern->type);
            $typeValue = $type?->value ?? $pattern->type;

            // Check if playbook exists
            $existingPlaybook = Playbook::where('proposal_type', $typeValue)
                ->where(function ($q) use ($pattern) {
                    $q->whereNull('project')
                        ->orWhere('project', $pattern->project);
                })
                ->exists();

            $successRate = $pattern->total > 0
                ? round(($pattern->successful / $pattern->total) * 100, 1)
                : 0;

            // Only suggest if success rate is good and no playbook exists
            if ($successRate >= 60 && ! $existingPlaybook) {
                $opportunities[] = [
                    'type' => $typeValue,
                    'type_label' => $type?->label() ?? $typeValue,
                    'project' => $pattern->project,
                    'count' => (int) $pattern->total,
                    'success_rate' => $successRate,
                    'has_playbook' => false,
                    'suggestion' => "Create a playbook for '{$type?->label()}' on {$pattern->project} ({$pattern->total} executions, {$successRate}% success)",
                ];
            }
        }

        // Sort by count descending
        usort($opportunities, fn ($a, $b) => $b['count'] <=> $a['count']);

        return $opportunities;
    }

    /**
     * Get system improvement suggestions based on analytics.
     *
     * @return array<string>
     */
    public function getImprovementSuggestions(): array
    {
        $suggestions = [];
        $metrics = $this->getOverallMetrics();
        $byType = $this->getMetricsByType();
        $playbookOpportunities = $this->detectPlaybookOpportunities();

        // Suggestion: Low approval rate
        if ($metrics['approval_rate'] < 50 && $metrics['total'] >= 5) {
            $suggestions[] = "Approval rate is {$metrics['approval_rate']}%. Consider refining research to propose higher-quality work.";
        }

        // Suggestion: Low execution success rate
        if ($metrics['execution_success_rate'] < 70 && $metrics['executed'] >= 3) {
            $suggestions[] = "Execution success rate is {$metrics['execution_success_rate']}%. Review failed tasks to improve prompt quality.";
        }

        // Suggestion: Playbook opportunities
        foreach (array_slice($playbookOpportunities, 0, 3) as $opportunity) {
            $suggestions[] = $opportunity['suggestion'];
        }

        // Suggestion: Types with 0% approval
        $failingTypes = $byType->filter(fn ($t) => $t['approval_rate'] === 0.0 && $t['total'] >= 2);
        foreach ($failingTypes as $type) {
            $suggestions[] = "'{$type['type_label']}' proposals have 0% approval ({$type['total']} rejected). Consider not proposing this type.";
        }

        // Suggestion: Slow decision time
        if ($metrics['avg_decision_time_hours'] > 24 && $metrics['total'] >= 5) {
            $suggestions[] = "Average decision time is {$metrics['avg_decision_time_hours']} hours. Consider using Telegram notifications for faster response.";
        }

        return $suggestions;
    }
}
