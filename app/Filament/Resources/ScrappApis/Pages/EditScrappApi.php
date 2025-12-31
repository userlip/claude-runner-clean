<?php

namespace App\Filament\Resources\ScrappApis\Pages;

use App\Filament\Resources\ScrappApis\ScrappApiResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditScrappApi extends EditRecord
{
    protected static string $resource = ScrappApiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
