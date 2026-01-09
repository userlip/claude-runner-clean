<?php

namespace App\Filament\Resources\Tasks\Pages;

use App\Filament\Resources\Tasks\TaskResource;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Support\Enums\Width;

class TaskIde extends Page
{
    use InteractsWithRecord;

    protected static string $resource = TaskResource::class;

    protected static ?string $slug = 'ide';

    protected string $view = 'filament.resources.tasks.task-resource.pages.task-ide';

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getTitle(): string
    {
        return 'IDE - '.($this->record->title ?? 'Task #'.$this->record->id);
    }

    public function getIdeUrl(): string
    {
        $folder = $this->record->workspace_path
            ?? $this->record->site?->path
            ?? '/home/ploi';

        return '/ide-proxy/?'.http_build_query([
            'folder' => $folder,
        ]);
    }
}
