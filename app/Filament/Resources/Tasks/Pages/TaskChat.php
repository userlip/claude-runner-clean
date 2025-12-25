<?php

namespace App\Filament\Resources\Tasks\Pages;

use App\Filament\Resources\Tasks\TaskResource;
use App\Models\Task;
use Filament\Resources\Pages\Page;

class TaskChat extends Page
{
    protected static string $resource = TaskResource::class;

    protected string $view = 'filament.resources.tasks.task-resource.pages.task-chat';

    public Task $record;

    public function mount($record): void
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
