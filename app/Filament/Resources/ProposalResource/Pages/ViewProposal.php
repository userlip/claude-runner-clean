<?php

namespace App\Filament\Resources\ProposalResource\Pages;

use App\Filament\Resources\ProposalResource;
use App\Jobs\RunPersonaSubtaskJob;
use App\Models\Proposal;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class ViewProposal extends ViewRecord
{
    protected static string $resource = ProposalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('approve_subtasks')
                ->label('Approve Subtasks')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Approve Subtasks')
                ->modalDescription(function (): string {
                    /** @var Proposal $proposal */
                    $proposal = $this->record;
                    $subtasks = $proposal->subtasks ?? [];
                    $lines = collect($subtasks)->map(fn (array $s, int $i) => ($i + 1).'. '.$s['title'])->implode("\n");

                    return "Approve the following subtasks for execution?\n\n".$lines;
                })
                ->visible(fn (Proposal $record): bool => $record->hasSubtasksPendingApproval())
                ->action(function (): void {
                    /** @var Proposal $proposal */
                    $proposal = $this->record;
                    $proposal->update(['subtasks_approved_at' => now()]);

                    Notification::make()
                        ->title('Subtasks approved')
                        ->success()
                        ->send();
                }),

            Actions\Action::make('resume_subtask')
                ->label('Resume Subtask')
                ->icon('heroicon-o-play')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Resume Failed Subtask')
                ->modalDescription(function (): string {
                    /** @var Proposal $proposal */
                    $proposal = $this->record;
                    $subtask = $proposal->getCurrentSubtask();

                    return "Retry the failed subtask: \"{$subtask['title']}\"?";
                })
                ->visible(fn (Proposal $record): bool => $record->hasFailedSubtask() && $record->executed_task_id !== null)
                ->action(function (): void {
                    /** @var Proposal $proposal */
                    $proposal = $this->record;
                    $task = $proposal->executedTask;

                    $proposal->updateSubtaskStatus($proposal->current_subtask_index, 'running');

                    RunPersonaSubtaskJob::dispatch($proposal->fresh(), $task);

                    Notification::make()
                        ->title('Subtask resumed')
                        ->success()
                        ->send();
                }),

            Actions\Action::make('skip_subtask')
                ->label('Skip Subtask')
                ->icon('heroicon-o-forward')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Skip Failed Subtask')
                ->modalDescription(function (): string {
                    /** @var Proposal $proposal */
                    $proposal = $this->record;
                    $subtask = $proposal->getCurrentSubtask();

                    return "Skip the subtask: \"{$subtask['title']}\" and advance to next?";
                })
                ->visible(fn (Proposal $record): bool => $record->hasFailedSubtask() && $record->executed_task_id !== null)
                ->action(function (): void {
                    /** @var Proposal $proposal */
                    $proposal = $this->record;
                    $task = $proposal->executedTask;

                    $currentIndex = $proposal->current_subtask_index;
                    $proposal->updateSubtaskStatus($currentIndex, 'skipped');

                    $totalSubtasks = count($proposal->subtasks);
                    $nextIndex = $currentIndex + 1;

                    if ($nextIndex >= $totalSubtasks) {
                        // All subtasks done (last one skipped)
                        app(\App\Services\PersonaCycleService::class)->completeExecution($proposal->fresh());

                        Notification::make()
                            ->title('Subtask skipped — all subtasks complete')
                            ->success()
                            ->send();
                    } else {
                        $proposal->advanceSubtaskIndex();
                        $proposal->refresh();
                        $proposal->updateSubtaskStatus($nextIndex, 'running');

                        RunPersonaSubtaskJob::dispatch($proposal->fresh(), $task);

                        Notification::make()
                            ->title('Subtask skipped — next subtask started')
                            ->success()
                            ->send();
                    }
                }),

            Actions\EditAction::make(),
        ];
    }

    public function content(Schema $schema): Schema
    {
        /** @var Proposal $proposal */
        $proposal = $this->record;

        $components = [
            $this->getFormContentComponent(),
        ];

        if (! empty($proposal->subtasks)) {
            $components[] = Section::make('Subtasks')
                ->icon('heroicon-o-list-bullet')
                ->schema([
                    View::make('filament.pages.proposal-subtasks')
                        ->viewData([
                            'subtasks' => $proposal->subtasks,
                            'currentIndex' => $proposal->current_subtask_index,
                        ]),
                ]);
        }

        if (! empty($proposal->data_appendix)) {
            $components[] = Section::make('Data Appendix')
                ->icon('heroicon-o-document-magnifying-glass')
                ->collapsed()
                ->schema([
                    View::make('filament.pages.proposal-data-appendix')
                        ->viewData(['dataAppendix' => $proposal->data_appendix]),
                ]);
        }

        return $schema->components($components);
    }
}
