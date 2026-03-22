<?php

namespace App\Livewire\Analytics;

use App\Enums\MessageRole;
use App\Enums\TaskStatus;
use App\Models\AnalyticsEvent;
use App\Models\Message;
use App\Models\Task;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Index extends Component
{
    public string $period = 'all';

    public array $dailyActivityChart = [];

    public array $providerDistributionChart = [];

    public array $costOverTimeChart = [];

    public array $agentHoursChart = [];

    public function mount(): void
    {
        $this->refreshCharts();
    }

    public function updatedPeriod(): void
    {
        unset(
            $this->stats,
            $this->providerStats,
            $this->topRepositories,
            $this->topTools,
        );

        $this->refreshCharts();
    }

    protected function deletedEventsQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = AnalyticsEvent::where('user_id', Auth::id())
            ->where('event_type', 'task_deleted');

        if ($this->period !== 'all') {
            $days = $this->periodDays();
            if ($days) {
                $query->where('task_created_at', '>=', now()->subDays($days));
            }
        }

        return $query;
    }

    #[Computed]
    public function stats(): array
    {
        $taskQuery = $this->userTasksQuery();

        $totalTasks = $taskQuery->count();
        $completedTasks = (clone $taskQuery)->where('status', TaskStatus::Completed)->count();
        $failedTasks = (clone $taskQuery)->where('status', TaskStatus::Failed)->count();

        $taskIds = (clone $taskQuery)->pluck('id');

        $messageStats = Message::whereIn('task_id', $taskIds)
            ->selectRaw('COUNT(*) as total_messages')
            ->selectRaw('SUM(tokens_in) as total_tokens_in')
            ->selectRaw('SUM(tokens_out) as total_tokens_out')
            ->selectRaw('SUM(cost_usd) as total_cost')
            ->first();

        $totalAgentSeconds = (clone $taskQuery)
            ->whereNotNull('started_at')
            ->whereNotNull('completed_at')
            ->selectRaw('SUM(TIMESTAMPDIFF(SECOND, started_at, completed_at)) as total_seconds')
            ->value('total_seconds') ?? 0;

        $compactions = (clone $taskQuery)->sum('compaction_count');
        $ralphTasks = (clone $taskQuery)->where('ralph_enabled', true)->count();
        $ralphIterations = (clone $taskQuery)->where('ralph_enabled', true)->sum('ralph_iteration');

        // Merge deleted task data from analytics_events
        $deleted = $this->deletedEventsQuery()
            ->selectRaw('COUNT(*) as cnt')
            ->selectRaw('SUM(messages_count) as messages')
            ->selectRaw('SUM(tokens_in) as tokens_in')
            ->selectRaw('SUM(tokens_out) as tokens_out')
            ->selectRaw('SUM(cost_usd) as cost')
            ->selectRaw('SUM(agent_seconds) as seconds')
            ->selectRaw('SUM(compaction_count) as compactions')
            ->selectRaw('SUM(ralph_iterations) as ralph_iters')
            ->first();

        return [
            'total_tasks' => $totalTasks + (int) ($deleted->cnt ?? 0),
            'completed_tasks' => $completedTasks,
            'failed_tasks' => $failedTasks,
            'deleted_tasks' => (int) ($deleted->cnt ?? 0),
            'total_messages' => (int) ($messageStats->total_messages ?? 0) + (int) ($deleted->messages ?? 0),
            'total_tokens_in' => (int) ($messageStats->total_tokens_in ?? 0) + (int) ($deleted->tokens_in ?? 0),
            'total_tokens_out' => (int) ($messageStats->total_tokens_out ?? 0) + (int) ($deleted->tokens_out ?? 0),
            'total_cost' => (float) ($messageStats->total_cost ?? 0) + (float) ($deleted->cost ?? 0),
            'total_agent_seconds' => (int) $totalAgentSeconds + (int) ($deleted->seconds ?? 0),
            'compactions' => (int) $compactions + (int) ($deleted->compactions ?? 0),
            'ralph_tasks' => $ralphTasks,
            'ralph_iterations' => (int) $ralphIterations + (int) ($deleted->ralph_iters ?? 0),
        ];
    }

    #[Computed]
    public function providerStats(): array
    {
        $taskIds = $this->userTasksQuery()->pluck('id');
        $results = [];

        if ($taskIds->isNotEmpty()) {
            $providerTasks = DB::table('tasks')
                ->join('ai_providers', 'tasks.ai_provider_id', '=', 'ai_providers.id')
                ->whereIn('tasks.id', $taskIds)
                ->whereNotNull('tasks.ai_provider_id')
                ->groupBy('ai_providers.id', 'ai_providers.display_name', 'ai_providers.name')
                ->selectRaw('ai_providers.id as provider_id, ai_providers.display_name, ai_providers.name as provider_key')
                ->selectRaw('COUNT(tasks.id) as task_count')
                ->selectRaw('SUM(CASE WHEN tasks.started_at IS NOT NULL AND tasks.completed_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, tasks.started_at, tasks.completed_at) ELSE 0 END) as total_seconds')
                ->orderByDesc('task_count')
                ->get();

            $messageStats = DB::table('messages')
                ->join('tasks', 'messages.task_id', '=', 'tasks.id')
                ->whereIn('tasks.id', $taskIds)
                ->whereNotNull('tasks.ai_provider_id')
                ->groupBy('tasks.ai_provider_id')
                ->selectRaw('tasks.ai_provider_id as provider_id')
                ->selectRaw('SUM(messages.tokens_in) as tokens_in')
                ->selectRaw('SUM(messages.tokens_out) as tokens_out')
                ->selectRaw('SUM(messages.cost_usd) as cost')
                ->get()
                ->keyBy('provider_id');

            foreach ($providerTasks as $row) {
                $ms = $messageStats->get($row->provider_id);
                $results[$row->display_name] = [
                    'name' => $row->display_name,
                    'key' => $row->provider_key,
                    'tasks' => (int) $row->task_count,
                    'seconds' => (int) $row->total_seconds,
                    'tokens_in' => (int) ($ms->tokens_in ?? 0),
                    'tokens_out' => (int) ($ms->tokens_out ?? 0),
                    'cost' => (float) ($ms->cost ?? 0),
                ];
            }
        }

        // Merge deleted task data by provider
        $deletedProviders = $this->deletedEventsQuery()
            ->whereNotNull('provider_name')
            ->groupBy('provider_name')
            ->selectRaw('provider_name')
            ->selectRaw('COUNT(*) as tasks')
            ->selectRaw('SUM(agent_seconds) as seconds')
            ->selectRaw('SUM(tokens_in) as tokens_in')
            ->selectRaw('SUM(tokens_out) as tokens_out')
            ->selectRaw('SUM(cost_usd) as cost')
            ->get();

        foreach ($deletedProviders as $row) {
            $name = $row->provider_name;
            if (isset($results[$name])) {
                $results[$name]['tasks'] += (int) $row->tasks;
                $results[$name]['seconds'] += (int) $row->seconds;
                $results[$name]['tokens_in'] += (int) $row->tokens_in;
                $results[$name]['tokens_out'] += (int) $row->tokens_out;
                $results[$name]['cost'] += (float) $row->cost;
            } else {
                $results[$name] = [
                    'name' => $name,
                    'key' => strtolower($name),
                    'tasks' => (int) $row->tasks,
                    'seconds' => (int) $row->seconds,
                    'tokens_in' => (int) $row->tokens_in,
                    'tokens_out' => (int) $row->tokens_out,
                    'cost' => (float) $row->cost,
                ];
            }
        }

        usort($results, fn ($a, $b) => $b['tasks'] <=> $a['tasks']);

        return $results;
    }

    #[Computed]
    public function topRepositories(): array
    {
        $results = [];

        $liveData = $this->userTasksQuery()
            ->whereNotNull('repository_id')
            ->join('repositories', 'tasks.repository_id', '=', 'repositories.id')
            ->groupBy('repositories.id', 'repositories.name')
            ->selectRaw('repositories.name')
            ->selectRaw('COUNT(tasks.id) as task_count')
            ->selectRaw('SUM(CASE WHEN tasks.started_at IS NOT NULL AND tasks.completed_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, tasks.started_at, tasks.completed_at) ELSE 0 END) as total_seconds')
            ->orderByDesc('task_count')
            ->limit(20)
            ->get();

        foreach ($liveData as $row) {
            $results[$row->name] = [
                'name' => $row->name,
                'tasks' => (int) $row->task_count,
                'seconds' => (int) $row->total_seconds,
            ];
        }

        // Merge deleted repos
        $deletedRepos = $this->deletedEventsQuery()
            ->whereNotNull('repository_name')
            ->groupBy('repository_name')
            ->selectRaw('repository_name, COUNT(*) as tasks, SUM(agent_seconds) as seconds')
            ->get();

        foreach ($deletedRepos as $row) {
            $name = $row->repository_name;
            if (isset($results[$name])) {
                $results[$name]['tasks'] += (int) $row->tasks;
                $results[$name]['seconds'] += (int) $row->seconds;
            } else {
                $results[$name] = [
                    'name' => $name,
                    'tasks' => (int) $row->tasks,
                    'seconds' => (int) $row->seconds,
                ];
            }
        }

        usort($results, fn ($a, $b) => $b['tasks'] <=> $a['tasks']);

        return array_slice($results, 0, 10);
    }

    #[Computed]
    public function topTools(): array
    {
        $taskIds = $this->userTasksQuery()->pluck('id');
        $toolCounts = [];

        // Live tasks
        $messages = Message::whereIn('task_id', $taskIds)
            ->whereNotNull('tool_calls')
            ->where('role', MessageRole::Assistant)
            ->pluck('tool_calls');

        foreach ($messages as $calls) {
            if (! is_array($calls)) {
                continue;
            }
            foreach ($calls as $call) {
                $name = $call['name'] ?? $call['tool'] ?? $call['type'] ?? 'unknown';
                $toolCounts[$name] = ($toolCounts[$name] ?? 0) + 1;
            }
        }

        // Deleted tasks
        $deletedTools = $this->deletedEventsQuery()
            ->whereNotNull('tool_usage')
            ->pluck('tool_usage');

        foreach ($deletedTools as $usage) {
            if (! is_array($usage)) {
                continue;
            }
            foreach ($usage as $tool => $count) {
                $toolCounts[$tool] = ($toolCounts[$tool] ?? 0) + (int) $count;
            }
        }

        arsort($toolCounts);

        return array_slice($toolCounts, 0, 15, true);
    }

    protected function userTasksQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = Task::query()
            ->where(function ($q) {
                $q->whereHas('repository', fn ($r) => $r->where('repositories.user_id', Auth::id()))
                    ->orWhere('tasks.user_id', Auth::id());
            });

        if ($this->period !== 'all') {
            $days = $this->periodDays();
            if ($days) {
                $query->where('created_at', '>=', now()->subDays($days));
            }
        }

        return $query;
    }

    protected function periodDays(): ?int
    {
        return match ($this->period) {
            '7 days' => 7,
            '30 days' => 30,
            '90 days' => 90,
            default => null,
        };
    }

    protected function chartDays(): int
    {
        return $this->periodDays() ?? 30;
    }

    protected function userTasksWhereClause(\Illuminate\Database\Query\Builder $q): void
    {
        $q->whereExists(function ($sub) {
            $sub->select(DB::raw(1))
                ->from('repositories')
                ->whereColumn('tasks.repository_id', 'repositories.id')
                ->where('repositories.user_id', Auth::id());
        })->orWhere('tasks.user_id', Auth::id());
    }

    protected function refreshCharts(): void
    {
        $this->dailyActivityChart = $this->buildDailyActivityChart();
        $this->providerDistributionChart = $this->buildProviderDistributionChart();
        $this->costOverTimeChart = $this->buildCostOverTimeChart();
        $this->agentHoursChart = $this->buildAgentHoursChart();
    }

    protected function buildDailyActivityChart(): array
    {
        $days = $this->chartDays();

        // Live tasks
        $liveRows = DB::table('tasks')
            ->where(function ($q) {
                $this->userTasksWhereClause($q);
            })
            ->where('tasks.created_at', '>=', now()->subDays($days))
            ->selectRaw('DATE(tasks.created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->get()
            ->keyBy('date');

        // Deleted tasks
        $deletedRows = AnalyticsEvent::where('user_id', Auth::id())
            ->where('event_type', 'task_deleted')
            ->where('task_created_at', '>=', now()->subDays($days))
            ->selectRaw('DATE(task_created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->get()
            ->keyBy('date');

        $labels = [];
        $data = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $labels[] = now()->subDays($i)->format('M d');
            $data[] = (int) ($liveRows[$date]->count ?? 0) + (int) ($deletedRows[$date]->count ?? 0);
        }

        return [
            'type' => 'bar',
            'data' => [
                'labels' => $labels,
                'datasets' => [[
                    'label' => 'Tasks',
                    'data' => $data,
                    'backgroundColor' => 'rgba(99, 102, 241, 0.6)',
                    'borderRadius' => 4,
                ]],
            ],
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'plugins' => ['legend' => ['display' => false]],
                'scales' => [
                    'x' => ['grid' => ['display' => false], 'ticks' => ['maxTicksLimit' => 10]],
                    'y' => ['beginAtZero' => true, 'ticks' => ['stepSize' => 1]],
                ],
            ],
        ];
    }

    protected function buildProviderDistributionChart(): array
    {
        $stats = $this->providerStats;
        $labels = array_column($stats, 'name');
        $data = array_column($stats, 'tasks');

        $colors = [
            'rgba(99, 102, 241, 0.8)',
            'rgba(16, 185, 129, 0.8)',
            'rgba(245, 158, 11, 0.8)',
            'rgba(239, 68, 68, 0.8)',
            'rgba(139, 92, 246, 0.8)',
            'rgba(20, 184, 166, 0.8)',
        ];

        return [
            'type' => 'doughnut',
            'data' => [
                'labels' => $labels,
                'datasets' => [[
                    'data' => $data,
                    'backgroundColor' => array_slice($colors, 0, count($labels)),
                ]],
            ],
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'plugins' => [
                    'legend' => ['position' => 'bottom', 'labels' => ['padding' => 16]],
                ],
            ],
        ];
    }

    protected function buildCostOverTimeChart(): array
    {
        $days = $this->chartDays();

        // Live cost
        $liveRows = DB::table('messages')
            ->join('tasks', 'messages.task_id', '=', 'tasks.id')
            ->where(function ($q) {
                $this->userTasksWhereClause($q);
            })
            ->where('messages.created_at', '>=', now()->subDays($days))
            ->selectRaw('DATE(messages.created_at) as date, SUM(messages.cost_usd) as cost')
            ->groupBy('date')
            ->get()
            ->keyBy('date');

        // Deleted task cost
        $deletedRows = AnalyticsEvent::where('user_id', Auth::id())
            ->where('event_type', 'task_deleted')
            ->where('task_created_at', '>=', now()->subDays($days))
            ->selectRaw('DATE(task_created_at) as date, SUM(cost_usd) as cost')
            ->groupBy('date')
            ->get()
            ->keyBy('date');

        $labels = [];
        $data = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $labels[] = now()->subDays($i)->format('M d');
            $data[] = round((float) ($liveRows[$date]->cost ?? 0) + (float) ($deletedRows[$date]->cost ?? 0), 2);
        }

        return [
            'type' => 'line',
            'data' => [
                'labels' => $labels,
                'datasets' => [[
                    'label' => 'Cost ($)',
                    'data' => $data,
                    'borderColor' => 'rgba(16, 185, 129, 1)',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                    'pointRadius' => 2,
                ]],
            ],
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'plugins' => ['legend' => ['display' => false]],
                'scales' => [
                    'x' => ['grid' => ['display' => false], 'ticks' => ['maxTicksLimit' => 10]],
                    'y' => ['beginAtZero' => true],
                ],
            ],
        ];
    }

    protected function buildAgentHoursChart(): array
    {
        $days = $this->chartDays();

        // Get all provider names (from DB + analytics events)
        $providerNames = DB::table('ai_providers')
            ->where('is_active', true)
            ->pluck('display_name', 'id')
            ->toArray();

        // Live: daily agent minutes per provider
        $liveRows = DB::table('tasks')
            ->where(function ($q) {
                $this->userTasksWhereClause($q);
            })
            ->whereNotNull('tasks.started_at')
            ->whereNotNull('tasks.completed_at')
            ->whereNotNull('tasks.ai_provider_id')
            ->where('tasks.started_at', '>=', now()->subDays($days))
            ->join('ai_providers', 'tasks.ai_provider_id', '=', 'ai_providers.id')
            ->selectRaw('DATE(tasks.started_at) as date')
            ->selectRaw('ai_providers.display_name as provider_name')
            ->selectRaw('SUM(TIMESTAMPDIFF(SECOND, tasks.started_at, tasks.completed_at)) / 60 as minutes')
            ->groupBy('date', 'ai_providers.display_name')
            ->get();

        // Deleted: daily agent minutes per provider
        $deletedRows = AnalyticsEvent::where('user_id', Auth::id())
            ->where('event_type', 'task_deleted')
            ->whereNotNull('provider_name')
            ->where('task_created_at', '>=', now()->subDays($days))
            ->selectRaw('DATE(task_created_at) as date, provider_name, SUM(agent_seconds) / 60 as minutes')
            ->groupBy('date', 'provider_name')
            ->get();

        // Merge into lookup: date => provider_name => minutes
        $lookup = [];
        $allProviders = [];
        foreach ($liveRows as $row) {
            $lookup[$row->date][$row->provider_name] = round((float) ($lookup[$row->date][$row->provider_name] ?? 0) + (float) $row->minutes, 1);
            $allProviders[$row->provider_name] = true;
        }
        foreach ($deletedRows as $row) {
            $lookup[$row->date][$row->provider_name] = round((float) ($lookup[$row->date][$row->provider_name] ?? 0) + (float) $row->minutes, 1);
            $allProviders[$row->provider_name] = true;
        }

        $labels = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $labels[] = now()->subDays($i)->format('M d');
        }

        $providerColors = [
            'rgba(99, 102, 241, 0.7)',
            'rgba(16, 185, 129, 0.7)',
            'rgba(245, 158, 11, 0.7)',
            'rgba(239, 68, 68, 0.7)',
            'rgba(139, 92, 246, 0.7)',
            'rgba(20, 184, 166, 0.7)',
        ];

        $datasets = [];
        $colorIdx = 0;
        foreach (array_keys($allProviders) as $providerName) {
            $data = [];
            for ($i = $days - 1; $i >= 0; $i--) {
                $date = now()->subDays($i)->format('Y-m-d');
                $data[] = $lookup[$date][$providerName] ?? 0;
            }

            if (array_sum($data) > 0) {
                $datasets[] = [
                    'label' => $providerName,
                    'data' => $data,
                    'backgroundColor' => $providerColors[$colorIdx % count($providerColors)],
                    'borderRadius' => 3,
                ];
                $colorIdx++;
            }
        }

        return [
            'type' => 'bar',
            'data' => [
                'labels' => $labels,
                'datasets' => $datasets,
            ],
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'plugins' => [
                    'legend' => ['position' => 'top', 'labels' => ['boxWidth' => 12, 'padding' => 12]],
                ],
                'scales' => [
                    'x' => ['stacked' => true, 'grid' => ['display' => false], 'ticks' => ['maxTicksLimit' => 10]],
                    'y' => ['stacked' => true, 'beginAtZero' => true, 'title' => ['display' => true, 'text' => 'Minutes']],
                ],
            ],
        ];
    }

    public function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds.'s';
        }
        if ($seconds < 3600) {
            return round($seconds / 60).'m';
        }

        $hours = floor($seconds / 3600);
        $minutes = round(($seconds % 3600) / 60);

        return $hours.'h '.$minutes.'m';
    }

    public function formatTokens(int $tokens): string
    {
        if ($tokens >= 1_000_000_000) {
            return round($tokens / 1_000_000_000, 1).'B';
        }
        if ($tokens >= 1_000_000) {
            return round($tokens / 1_000_000, 1).'M';
        }
        if ($tokens >= 1_000) {
            return round($tokens / 1_000, 1).'K';
        }

        return (string) $tokens;
    }

    public function render(): View
    {
        return view('livewire.analytics.index');
    }
}
