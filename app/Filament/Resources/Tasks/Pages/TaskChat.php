<?php

namespace App\Filament\Resources\Tasks\Pages;

use App\Filament\Resources\Tasks\TaskResource;
use App\Models\Task;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Support\Enums\Width;

class TaskChat extends Page
{
    use InteractsWithRecord;

    protected static string $resource = TaskResource::class;

    protected string $view = 'filament.resources.tasks.task-resource.pages.task-chat';

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
        $location = $this->record->site
            ? $this->record->site->domain
            : 'Workspace';

        return "{$this->record->repository->name} - {$location}";
    }
}
