<?php

namespace App\Filament\Resources\Tasks\Pages;

use App\Filament\Resources\Tasks\TaskResource;
use App\Jobs\CloneRepositoryJob;
use App\Models\Repository;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateTask extends CreateRecord
{
    protected static string $resource = TaskResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $workLocation = $data['work_location'] ?? 'workspace';
        unset($data['work_location']);

        if ($workLocation === 'workspace') {
            $repository = Repository::find($data['repository_id']);
            $data['workspace_path'] = '/home/ploi/workspaces/'.Str::slug($repository->name).'-'.Str::random(8);
            $data['site_id'] = null;
        } else {
            // Extract site ID from 'site_123' format
            $siteId = (int) str_replace('site_', '', $workLocation);
            $data['site_id'] = $siteId;
            $data['workspace_path'] = null;
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        if ($this->record->workspace_path) {
            CloneRepositoryJob::dispatch($this->record);
        }
    }

    protected function getRedirectUrl(): string
    {
        return TaskResource::getUrl('chat', ['record' => $this->record]);
    }
}
