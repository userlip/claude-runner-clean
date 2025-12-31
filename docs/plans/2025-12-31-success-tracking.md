# Success Tracking Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Track proposal approval/rejection patterns and execution outcomes to help AI learn user preferences.

**Architecture:** Add tracking fields to proposals table, create ProposalAnalyticsService for metrics, add Filament widget to display stats on dashboard.

**Tech Stack:** Laravel 12, PHP 8.4, Filament v4

---

## Task 1: Add tracking columns to proposals table

**Files:**
- Create: `database/migrations/2025_12_31_220000_add_tracking_to_proposals_table.php`

**Step 1: Create migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proposals', function (Blueprint $table) {
            $table->text('rejected_reason')->nullable()->after('rejected_at');
            $table->unsignedInteger('decision_time_seconds')->nullable()->after('rejected_reason');
            $table->timestamp('execution_completed_at')->nullable()->after('decision_time_seconds');
            $table->boolean('execution_success')->nullable()->after('execution_completed_at');
            $table->unsignedInteger('follow_up_count')->default(0)->after('execution_success');
        });
    }

    public function down(): void
    {
        Schema::table('proposals', function (Blueprint $table) {
            $table->dropColumn([
                'rejected_reason',
                'decision_time_seconds',
                'execution_completed_at',
                'execution_success',
                'follow_up_count',
            ]);
        });
    }
};
```

**Step 2: Run migration**

```bash
php artisan migrate
```

**Step 3: Commit**

```bash
git add database/migrations/*add_tracking_to_proposals*
git commit -m "feat: add tracking columns to proposals table"
```

---

## Task 2: Update Proposal model with tracking fields

**Files:**
- Modify: `app/Models/Proposal.php`

**Step 1: Add to $fillable array**

Add these fields:
```php
'rejected_reason',
'decision_time_seconds',
'execution_completed_at',
'execution_success',
'follow_up_count',
```

**Step 2: Add to casts() method**

```php
'execution_completed_at' => 'datetime',
'execution_success' => 'boolean',
'decision_time_seconds' => 'integer',
'follow_up_count' => 'integer',
```

**Step 3: Update approve() method to track decision time**

```php
public function approve(): void
{
    $decisionTime = $this->created_at->diffInSeconds(now());

    $this->update([
        'status' => ProposalStatus::Approved,
        'approved_at' => now(),
        'decision_time_seconds' => $decisionTime,
    ]);

    // Trigger autonomous execution
    $service = app(\App\Services\ProposalExecutionService::class);
    $service->execute($this);
}
```

**Step 4: Update reject() method to track decision time and reason**

```php
public function reject(?string $reason = null): void
{
    $decisionTime = $this->created_at->diffInSeconds(now());

    $this->update([
        'status' => ProposalStatus::Rejected,
        'rejected_at' => now(),
        'rejected_reason' => $reason,
        'decision_time_seconds' => $decisionTime,
    ]);
}
```

**Step 5: Add helper methods**

```php
public function markExecutionComplete(bool $success): void
{
    $this->update([
        'execution_completed_at' => now(),
        'execution_success' => $success,
    ]);
}

public function incrementFollowUpCount(): void
{
    $this->increment('follow_up_count');
}

public function getApprovalRateAttribute(): ?float
{
    // This is for display purposes on individual proposals
    return null; // Calculated at aggregate level
}
```

**Step 6: Verify syntax**

```bash
php -l app/Models/Proposal.php
```

**Step 7: Commit**

```bash
git add app/Models/Proposal.php
git commit -m "feat: add tracking methods to Proposal model"
```

---

## Task 3: Create ProposalAnalyticsService

**Files:**
- Create: `app/Services/ProposalAnalyticsService.php`

**Step 1: Create the service**

```php
<?php

namespace App\Services;

use App\Enums\ProposalStatus;
use App\Enums\ProposalType;
use App\Models\Proposal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProposalAnalyticsService
{
    public function getOverallMetrics(): array
    {
        $total = Proposal::count();
        $approved = Proposal::where('status', ProposalStatus::Approved)->count();
        $rejected = Proposal::where('status', ProposalStatus::Rejected)->count();
        $pending = Proposal::where('status', ProposalStatus::Pending)->count();

        $executed = Proposal::whereNotNull('execution_completed_at')->count();
        $successful = Proposal::where('execution_success', true)->count();

        $avgDecisionTime = Proposal::whereNotNull('decision_time_seconds')
            ->avg('decision_time_seconds');

        return [
            'total' => $total,
            'approved' => $approved,
            'rejected' => $rejected,
            'pending' => $pending,
            'approval_rate' => $total > 0 ? round(($approved / max($approved + $rejected, 1)) * 100, 1) : 0,
            'executed' => $executed,
            'successful' => $successful,
            'execution_success_rate' => $executed > 0 ? round(($successful / $executed) * 100, 1) : 0,
            'avg_decision_time_hours' => $avgDecisionTime ? round($avgDecisionTime / 3600, 1) : null,
        ];
    }

    public function getMetricsByType(): Collection
    {
        return Proposal::select('type')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as approved', [ProposalStatus::Approved->value])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as rejected', [ProposalStatus::Rejected->value])
            ->selectRaw('SUM(CASE WHEN execution_success = 1 THEN 1 ELSE 0 END) as successful')
            ->selectRaw('SUM(CASE WHEN execution_completed_at IS NOT NULL THEN 1 ELSE 0 END) as executed')
            ->groupBy('type')
            ->get()
            ->map(function ($row) {
                $decided = $row->approved + $row->rejected;
                return [
                    'type' => $row->type,
                    'type_label' => ProposalType::tryFrom($row->type)?->label() ?? $row->type,
                    'total' => $row->total,
                    'approved' => $row->approved,
                    'rejected' => $row->rejected,
                    'approval_rate' => $decided > 0 ? round(($row->approved / $decided) * 100, 1) : 0,
                    'executed' => $row->executed,
                    'successful' => $row->successful,
                    'success_rate' => $row->executed > 0 ? round(($row->successful / $row->executed) * 100, 1) : 0,
                ];
            });
    }

    public function getMetricsByProject(): Collection
    {
        return Proposal::select('project')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as approved', [ProposalStatus::Approved->value])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as rejected', [ProposalStatus::Rejected->value])
            ->selectRaw('AVG(decision_time_seconds) as avg_decision_time')
            ->groupBy('project')
            ->get()
            ->map(function ($row) {
                $decided = $row->approved + $row->rejected;
                return [
                    'project' => $row->project,
                    'total' => $row->total,
                    'approved' => $row->approved,
                    'rejected' => $row->rejected,
                    'approval_rate' => $decided > 0 ? round(($row->approved / $decided) * 100, 1) : 0,
                    'avg_decision_time_hours' => $row->avg_decision_time ? round($row->avg_decision_time / 3600, 1) : null,
                ];
            });
    }

    public function getTopRejectionReasons(int $limit = 5): Collection
    {
        return Proposal::whereNotNull('rejected_reason')
            ->where('rejected_reason', '!=', '')
            ->select('rejected_reason')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('rejected_reason')
            ->orderByDesc('count')
            ->limit($limit)
            ->get();
    }

    public function getRecentTrend(int $days = 30): array
    {
        $startDate = now()->subDays($days);

        $daily = Proposal::where('created_at', '>=', $startDate)
            ->selectRaw('DATE(created_at) as date')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as approved', [ProposalStatus::Approved->value])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();

        return [
            'labels' => $daily->pluck('date')->toArray(),
            'total' => $daily->pluck('total')->toArray(),
            'approved' => $daily->pluck('approved')->toArray(),
        ];
    }

    /**
     * Get insights for research prompts - which types/projects to prioritize
     */
    public function getResearchPriorities(): array
    {
        $byType = $this->getMetricsByType()
            ->filter(fn ($m) => $m['total'] >= 3) // Need enough data
            ->sortByDesc('approval_rate')
            ->take(3);

        $byProject = $this->getMetricsByProject()
            ->filter(fn ($m) => $m['total'] >= 3)
            ->sortByDesc('approval_rate')
            ->take(3);

        return [
            'preferred_types' => $byType->pluck('type')->toArray(),
            'preferred_projects' => $byProject->pluck('project')->toArray(),
            'avoid_types' => $this->getMetricsByType()
                ->filter(fn ($m) => $m['total'] >= 3 && $m['approval_rate'] < 30)
                ->pluck('type')
                ->toArray(),
        ];
    }
}
```

**Step 2: Verify syntax**

```bash
php -l app/Services/ProposalAnalyticsService.php
```

**Step 3: Commit**

```bash
git add app/Services/ProposalAnalyticsService.php
git commit -m "feat: add ProposalAnalyticsService for tracking metrics"
```

---

## Task 4: Create Filament Analytics Widget

**Files:**
- Create: `app/Filament/Widgets/ProposalAnalyticsWidget.php`

**Step 1: Create the widget**

```php
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
```

**Step 2: Verify syntax**

```bash
php -l app/Filament/Widgets/ProposalAnalyticsWidget.php
```

**Step 3: Commit**

```bash
git add app/Filament/Widgets/ProposalAnalyticsWidget.php
git commit -m "feat: add ProposalAnalyticsWidget for dashboard"
```

---

## Task 5: Update ProposalResource to show rejection reason field

**Files:**
- Modify: `app/Filament/Resources/ProposalResource.php`

**Step 1: Add rejection reason to reject action**

Find the reject action in the table and update it to prompt for a reason:

```php
Actions\Action::make('reject')
    ->icon('heroicon-o-x-circle')
    ->color('danger')
    ->requiresConfirmation()
    ->form([
        \Filament\Forms\Components\Textarea::make('reason')
            ->label('Rejection Reason')
            ->placeholder('Optional: Why are you rejecting this proposal?')
            ->rows(2),
    ])
    ->action(function (Proposal $record, array $data) {
        $record->reject($data['reason'] ?? null);
        \Filament\Notifications\Notification::make()
            ->title('Proposal rejected')
            ->success()
            ->send();
    })
    ->visible(fn (Proposal $record) => $record->status === \App\Enums\ProposalStatus::Pending),
```

**Step 2: Add tracking columns to table (toggleable)**

```php
Tables\Columns\TextColumn::make('decision_time_seconds')
    ->label('Decision Time')
    ->formatStateUsing(fn ($state) => $state ? round($state / 3600, 1).'h' : '-')
    ->toggleable(isToggledHiddenByDefault: true),

Tables\Columns\TextColumn::make('execution_success')
    ->label('Success')
    ->badge()
    ->color(fn ($state) => match ($state) {
        true => 'success',
        false => 'danger',
        default => 'gray',
    })
    ->formatStateUsing(fn ($state) => match ($state) {
        true => 'Yes',
        false => 'No',
        default => '-',
    })
    ->toggleable(isToggledHiddenByDefault: true),
```

**Step 3: Commit**

```bash
git add app/Filament/Resources/ProposalResource.php
git commit -m "feat: add rejection reason and tracking columns to ProposalResource"
```

---

## Task 6: Update task completion to track execution success

**Files:**
- Modify: `app/Models/Task.php`
- Modify: `app/Jobs/RunClaudeMessageJob.php` (if needed)

**Step 1: Add method to Task model to update proposal on completion**

```php
public function updateProposalExecution(bool $success): void
{
    // Find proposal that executed this task
    $proposal = \App\Models\Proposal::where('executed_task_id', $this->id)->first();

    if ($proposal) {
        $proposal->markExecutionComplete($success);
    }
}
```

**Step 2: In Task::markAsCompleted(), call the update**

Update the existing method:

```php
public function markAsCompleted(): void
{
    $this->update([
        'status' => TaskStatus::Completed,
        'completed_at' => now(),
    ]);

    $this->updateProposalExecution(true);
}
```

**Step 3: In Task::markAsFailed(), call the update**

```php
public function markAsFailed(): void
{
    $this->update([
        'status' => TaskStatus::Failed,
        'completed_at' => now(),
    ]);

    $this->updateProposalExecution(false);
}
```

**Step 4: Verify syntax**

```bash
php -l app/Models/Task.php
```

**Step 5: Commit**

```bash
git add app/Models/Task.php
git commit -m "feat: track proposal execution success on task completion"
```

---

## Task 7: Add analytics command for CLI access

**Files:**
- Create: `app/Console/Commands/ProposalAnalyticsCommand.php`

**Step 1: Create the command**

```php
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
```

**Step 2: Verify syntax**

```bash
php -l app/Console/Commands/ProposalAnalyticsCommand.php
```

**Step 3: Commit**

```bash
git add app/Console/Commands/ProposalAnalyticsCommand.php
git commit -m "feat: add proposal:analytics command for CLI metrics"
```

---

## Task 8: Inject analytics into research prompts

**Files:**
- Modify: `app/Services/ResearchService.php`

**Step 1: Update injectPromptData to include analytics priorities**

Add method:

```php
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
```

**Step 2: Update injectPromptData to call it**

```php
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
```

**Step 3: Verify syntax**

```bash
php -l app/Services/ResearchService.php
```

**Step 4: Commit**

```bash
git add app/Services/ResearchService.php
git commit -m "feat: inject analytics priorities into research prompts"
```

---

## Task 9: Final verification

**Step 1: Run all migrations**

```bash
php artisan migrate
```

**Step 2: Verify analytics command**

```bash
php artisan proposal:analytics
```

**Step 3: Verify widget loads**

```bash
php artisan tinker --execute="app(\App\Filament\Widgets\ProposalAnalyticsWidget::class);"
```

**Step 4: Final commit**

```bash
git add -A
git commit -m "feat: complete Phase 5.1 Success Tracking implementation"
```

---

## Summary

This implementation enables:

1. **Tracking**: Decision time, rejection reasons, execution success
2. **Analytics Service**: Overall metrics, by type, by project, trends
3. **Dashboard Widget**: Visual stats on approval rate, success rate, decision time
4. **CLI Command**: `proposal:analytics` for terminal access
5. **Research Integration**: Prompts automatically include user preferences

The AI now "learns" by seeing which proposal types and projects have high approval rates, and avoids suggesting types with low approval.
