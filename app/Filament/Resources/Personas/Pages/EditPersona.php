<?php

namespace App\Filament\Resources\Personas\Pages;

use App\Enums\PersonaStatus;
use App\Filament\Resources\Personas\PersonaResource;
use App\Filament\Resources\Personas\Widgets\PersonaStatsWidget;
use App\Jobs\RunPersonaCycleJob;
use App\Models\Persona;
use App\Services\PersonaStorageService;
use Filament\Actions;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class EditPersona extends EditRecord
{
    protected static string $resource = PersonaResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            PersonaStatsWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('view_storage')
                ->label('View Storage')
                ->icon('heroicon-o-folder-open')
                ->color('gray')
                ->modalHeading('Persona Storage')
                ->modalWidth('4xl')
                ->modalSubmitAction(false)
                ->modalContent(function (): \Illuminate\Contracts\View\View {
                    /** @var Persona $persona */
                    $persona = $this->record;
                    $storageService = app(PersonaStorageService::class);

                    return view('filament.pages.persona-storage-viewer', [
                        'tree' => $storageService->getDirectoryTree($persona),
                        'persona' => $persona,
                        'storageService' => $storageService,
                    ]);
                }),

            Actions\Action::make('edit_context')
                ->label('Edit Context')
                ->icon('heroicon-o-pencil-square')
                ->color('warning')
                ->modalHeading('Edit Context')
                ->modalWidth('4xl')
                ->form([
                    \Filament\Forms\Components\Textarea::make('context_content')
                        ->label('context.md')
                        ->rows(20)
                        ->default(function (): string {
                            /** @var Persona $persona */
                            $persona = $this->record;

                            return app(PersonaStorageService::class)->readContext($persona) ?? '';
                        }),
                ])
                ->action(function (array $data): void {
                    /** @var Persona $persona */
                    $persona = $this->record;

                    app(PersonaStorageService::class)->writeContext($persona, $data['context_content']);

                    Notification::make()
                        ->title('Context updated')
                        ->success()
                        ->send();
                }),

            Actions\Action::make('run_now')
                ->label('Run Now')
                ->icon('heroicon-o-play-circle')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Run Analysis Cycle')
                ->modalDescription('This will dispatch an analysis cycle for this persona immediately.')
                ->disabled(fn (): bool => ! $this->record->is_active || $this->record->status === PersonaStatus::Running)
                ->action(function (): void {
                    /** @var Persona $record */
                    $record = $this->record;
                    RunPersonaCycleJob::dispatch($record);

                    Notification::make()
                        ->title('Analysis cycle dispatched')
                        ->body("Running analysis for {$record->name}")
                        ->success()
                        ->send();
                }),

            Actions\Action::make('toggle_active')
                ->label(fn (): string => $this->record->is_active ? 'Deactivate' : 'Activate')
                ->icon(fn (): string => $this->record->is_active ? 'heroicon-o-pause' : 'heroicon-o-play')
                ->color(fn (): string => $this->record->is_active ? 'gray' : 'success')
                ->requiresConfirmation()
                ->action(function (): void {
                    /** @var Persona $record */
                    $record = $this->record;
                    $newActive = ! $record->is_active;

                    $record->update([
                        'is_active' => $newActive,
                        'status' => $newActive ? PersonaStatus::Active : PersonaStatus::Paused,
                    ]);

                    Notification::make()
                        ->title($newActive ? 'Persona activated' : 'Persona deactivated')
                        ->success()
                        ->send();
                }),
            DeleteAction::make(),
        ];
    }

    public function content(Schema $schema): Schema
    {
        /** @var Persona $persona */
        $persona = $this->record;
        $storageService = app(PersonaStorageService::class);

        $contextContent = $storageService->readContext($persona);
        $historyFiles = $storageService->getHistoryFiles($persona);

        $components = [
            $this->getFormContentComponent(),
            $this->getRelationManagersContentComponent(),
        ];

        if ($contextContent) {
            $components[] = Section::make('Context')
                ->icon('heroicon-o-document-text')
                ->collapsed()
                ->schema([
                    View::make('filament.pages.persona-context-section')
                        ->viewData(['contextContent' => $contextContent]),
                ]);
        }

        if (! empty($historyFiles)) {
            $components[] = Section::make('Run History')
                ->icon('heroicon-o-clock')
                ->collapsed()
                ->schema([
                    View::make('filament.pages.persona-run-history')
                        ->viewData(['historyFiles' => $historyFiles]),
                ]);
        }

        return $schema->components($components);
    }
}
