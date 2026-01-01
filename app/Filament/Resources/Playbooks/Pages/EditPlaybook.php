<?php

namespace App\Filament\Resources\Playbooks\Pages;

use App\Filament\Resources\Playbooks\PlaybookResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPlaybook extends EditRecord
{
    protected static string $resource = PlaybookResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
