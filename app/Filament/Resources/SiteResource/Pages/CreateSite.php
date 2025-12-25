<?php

namespace App\Filament\Resources\SiteResource\Pages;

use App\Filament\Resources\SiteResource;
use App\Jobs\ProvisionSiteJob;
use Filament\Resources\Pages\CreateRecord;

class CreateSite extends CreateRecord
{
    protected static string $resource = SiteResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        unset($data['create_database'], $data['run_composer']);

        return $data;
    }

    protected function afterCreate(): void
    {
        ProvisionSiteJob::dispatch($this->record);
    }
}
