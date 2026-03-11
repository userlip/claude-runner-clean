<?php

namespace App\Filament\Resources\Personas\Widgets;

use App\Models\Persona;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;

class PersonaStatsWidget extends StatsOverviewWidget
{
    public ?Model $record = null;

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        /** @var Persona $persona */
        $persona = $this->record;

        $approvedCount = $persona->proposals()->where('status', 'approved')->count();
        $totalDecided = $persona->proposals()->whereIn('status', ['approved', 'rejected'])->count();
        $approvalRate = $totalDecided > 0 ? round(($approvedCount / $totalDecided) * 100) : 0;

        return [
            Stat::make('Total Runs', (string) $persona->total_runs)
                ->icon('heroicon-o-arrow-path')
                ->color('primary'),

            Stat::make('Total Proposals', (string) $persona->total_proposals)
                ->icon('heroicon-o-inbox-arrow-down')
                ->color('info'),

            Stat::make('Approval Rate', $totalDecided > 0 ? "{$approvalRate}%" : 'N/A')
                ->description($totalDecided > 0 ? "{$approvedCount}/{$totalDecided} approved" : 'No decisions yet')
                ->icon('heroicon-o-check-circle')
                ->color($approvalRate >= 70 ? 'success' : ($approvalRate >= 50 ? 'warning' : 'gray')),

            Stat::make('Last Run', $persona->last_run_at?->diffForHumans() ?? 'Never')
                ->icon('heroicon-o-clock')
                ->color($persona->last_run_at ? 'success' : 'gray'),
        ];
    }
}
