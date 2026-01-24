<?php

namespace App\Filament\Resources;

use App\Enums\SecurityRunStatus;
use App\Filament\Resources\SecurityRunResource\Pages;
use App\Filament\Resources\Tasks\TaskResource;
use App\Models\SecurityRun;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SecurityRunResource extends Resource
{
    protected static ?string $model = SecurityRun::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = 'Security Dashboard';

    protected static ?int $navigationSort = 6;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('pr_title')
                    ->label('PR')
                    ->description(fn (SecurityRun $record): string => "#{$record->github_pr_number}")
                    ->searchable()
                    ->limit(50)
                    ->url(fn (SecurityRun $record): ?string => $record->repository
                        ? "https://github.com/{$record->repository->full_name}/pull/{$record->github_pr_number}"
                        : null)
                    ->openUrlInNewTab(),

                Tables\Columns\TextColumn::make('repository.name')
                    ->label('Repo')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (SecurityRunStatus|string $state): string => match ($state instanceof SecurityRunStatus ? $state->value : $state) {
                        'deployed' => 'Deployed',
                        'merged' => 'Merged',
                        'approved' => 'Approved',
                        'closed' => 'Closed',
                        'failed' => 'Failed',
                        'needs_user_action' => 'Needs Input',
                        'fixing_ci' => 'Fixing CI',
                        'waiting_ci' => 'Waiting CI',
                        'researching' => 'Researching',
                        'pending' => 'Pending',
                        default => $state instanceof SecurityRunStatus ? $state->value : $state,
                    })
                    ->color(fn (SecurityRunStatus|string $state): string => match ($state instanceof SecurityRunStatus ? $state->value : $state) {
                        'deployed' => 'success',
                        'merged' => 'info',
                        'approved' => 'info',
                        'closed' => 'success',  // User decided to close - that's a successful resolution
                        'failed' => 'danger',
                        'needs_user_action' => 'warning',
                        'fixing_ci' => 'warning',
                        'waiting_ci' => 'gray',
                        'researching' => 'gray',
                        'pending' => 'gray',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('risk_level')
                    ->label('Risk')
                    ->badge()
                    ->color(fn (?string $state): string => match (strtolower($state ?? '')) {
                        'low' => 'success',
                        'medium' => 'warning',
                        'high' => 'danger',
                        'major' => 'danger',
                        default => 'gray',
                    })
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('show_completed')
                    ->label('Show completed')
                    ->placeholder('Active only')
                    ->trueLabel('All runs')
                    ->falseLabel('Active only')
                    ->default(false)
                    ->queries(
                        true: fn ($query) => $query,
                        false: fn ($query) => $query->whereNotIn('status', [
                            SecurityRunStatus::Deployed->value,
                            SecurityRunStatus::Merged->value,
                            SecurityRunStatus::Closed->value,
                        ]),
                        blank: fn ($query) => $query->whereNotIn('status', [
                            SecurityRunStatus::Deployed->value,
                            SecurityRunStatus::Merged->value,
                            SecurityRunStatus::Closed->value,
                        ]),
                    ),
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(SecurityRunStatus::cases())
                        ->mapWithKeys(fn (SecurityRunStatus $status) => [$status->value => $status->name])
                        ->all()),
                Tables\Filters\SelectFilter::make('repository')
                    ->relationship('repository', 'name'),
            ])
            ->recordActions([
                Action::make('viewChat')
                    ->label('View Chat')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->url(fn (SecurityRun $record): ?string => $record->repository?->securityTask
                        ? TaskResource::getUrl('chat', ['record' => $record->repository->securityTask->uuid])
                        : null)
                    ->visible(fn (SecurityRun $record): bool => $record->repository?->securityTask !== null),

                Action::make('retry')
                    ->label('Retry')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Retry Security Run')
                    ->modalDescription('This will reset the run to "Pending" status and re-evaluate the PR with updated guidelines.')
                    ->action(function (SecurityRun $record): void {
                        $record->update([
                            'status' => SecurityRunStatus::Pending,
                            'decision_summary' => null,
                            'error_message' => null,
                            'risk_level' => null,
                        ]);
                    })
                    ->visible(fn (SecurityRun $record): bool => $record->status === SecurityRunStatus::Failed),

                Action::make('viewOnGitHub')
                    ->label('GitHub')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (SecurityRun $record): ?string => $record->repository
                        ? "https://github.com/{$record->repository->full_name}/pull/{$record->github_pr_number}"
                        : null)
                    ->openUrlInNewTab(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->poll('10s');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSecurityRuns::route('/'),
        ];
    }
}
