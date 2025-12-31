<?php

namespace App\Services;

use App\Enums\ProposalStatus;
use App\Enums\ProposalType;
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
}
