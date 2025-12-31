<?php

namespace App\Filament\Resources\ScrappApis\Pages;

use App\Filament\Resources\ScrappApis\ScrappApiResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListScrappApis extends ListRecords
{
    protected static string $resource = ScrappApiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
