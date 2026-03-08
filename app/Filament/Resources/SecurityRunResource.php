<?php

namespace App\Filament\Resources;

use App\Enums\SecurityRunStatus;
use App\Filament\Resources\SecurityRunResource\Pages;
use App\Filament\Resources\Tasks\TaskResource;
use App\Jobs\RunSecurityManagementJob;
use App\Models\Repository;
use App\Models\SecurityRun;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\File;

class SecurityRunResource extends Resource
{
    protected static ?string $model = SecurityRun::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = 'Security Dashboard';

    protected static ?int $navigationSort = 6;

    public static function getNavigationBadge(): ?string
    {
        return (string) SecurityRun::whereHas('task')->count();
    }

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

                Tables\Columns\TextColumn::make('from_version')
                    ->label('From')
                    ->badge()
                    ->color('gray')
                    ->placeholder('-')
                    ->sortable(),

                Tables\Columns\TextColumn::make('to_version')
                    ->label('To')
                    ->badge()
                    ->color('success')
                    ->placeholder('-')
                    ->sortable(),

                Tables\Columns\TextColumn::make('repository.name')
                    ->label('Repo')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable()
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
                        'closed' => 'danger',
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

                Tables\Columns\TextColumn::make('task.aiProvider.name')
                    ->label('AI')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state ? strtoupper($state) : '-')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('error_message')
                    ->label('Error')
                    ->limit(60)
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('show_deleted_chats')
                    ->label('Show deleted chats')
                    ->placeholder('Hide deleted')
                    ->trueLabel('Show all')
                    ->falseLabel('Hide deleted')
                    ->default(false)
                    ->queries(
                        true: fn ($query) => $query,
                        false: fn ($query) => $query->whereHas('task'),
                        blank: fn ($query) => $query->whereHas('task'),
                    ),
                Tables\Filters\TernaryFilter::make('show_completed')
                    ->label('Show completed')
                    ->placeholder('Active only')
                    ->trueLabel('All runs')
                    ->falseLabel('Active only')
                    ->default(true)
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
                    ->url(fn (SecurityRun $record): ?string => $record->task
                        ? TaskResource::getUrl('chat', ['record' => $record->task->uuid])
                        : null)
                    ->visible(fn (SecurityRun $record): bool => $record->task !== null),

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
            ->toolbarActions([
                Action::make('rerunStuck')
                    ->label('Rerun Stuck')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Rerun Stuck Security Runs')
                    ->modalDescription('Reset active/stuck runs to Pending and queue orchestration for all security-enabled repositories.')
                    ->action(function (): void {
                        $resetCount = SecurityRun::query()
                            ->whereIn('status', [
                                SecurityRunStatus::Pending->value,
                                SecurityRunStatus::WaitingCi->value,
                                SecurityRunStatus::FixingCi->value,
                                SecurityRunStatus::Researching->value,
                                SecurityRunStatus::NeedsUserAction->value,
                                SecurityRunStatus::Failed->value,
                            ])
                            ->update([
                                'status' => SecurityRunStatus::Pending->value,
                                'decision_summary' => null,
                                'risk_level' => null,
                                'error_message' => null,
                            ]);

                        $repoIds = Repository::query()
                            ->where('security_management_enabled', true)
                            ->pluck('id');

                        foreach ($repoIds as $repoId) {
                            RunSecurityManagementJob::dispatch($repoId);
                        }

                        Notification::make()
                            ->title('Security rerun queued')
                            ->body("Reset {$resetCount} runs and queued {$repoIds->count()} repositories.")
                            ->success()
                            ->send();
                    }),
                BulkActionGroup::make([
                    BulkAction::make('deleteChatAndWorkspace')
                        ->label('Delete Chat & Workspace')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Delete Chat & Workspace')
                        ->modalDescription('This will permanently delete the selected tasks, their workspaces from the server, and all associated data.')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records): void {
                            $deletedCount = 0;
                            $workspaceCount = 0;

                            foreach ($records as $record) {
                                if ($record->task) {
                                    // Delete workspace from server if exists
                                    if ($record->task->workspace_path && File::isDirectory($record->task->workspace_path)) {
                                        File::deleteDirectory($record->task->workspace_path);
                                        $workspaceCount++;
                                    }

                                    // Delete the task (cascades to messages, etc.)
                                    $record->task->delete();
                                    $deletedCount++;
                                }
                            }

                            Notification::make()
                                ->title('Deletion complete')
                                ->body("Deleted {$deletedCount} chats and {$workspaceCount} workspaces.")
                                ->success()
                                ->send();
                        }),

                    BulkAction::make('deleteWorkspaceOnly')
                        ->label('Delete Workspace Only')
                        ->icon('heroicon-o-folder-minus')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Delete Workspace Only')
                        ->modalDescription('This will delete the workspace directories from the server but keep the chat history in the database.')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records): void {
                            $workspaceCount = 0;

                            foreach ($records as $record) {
                                if ($record->task && $record->task->workspace_path && File::isDirectory($record->task->workspace_path)) {
                                    File::deleteDirectory($record->task->workspace_path);
                                    $record->task->update(['workspace_path' => null]);
                                    $workspaceCount++;
                                }
                            }

                            Notification::make()
                                ->title('Workspaces deleted')
                                ->body("Deleted {$workspaceCount} workspaces from the server.")
                                ->success()
                                ->send();
                        }),
                ]),
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
