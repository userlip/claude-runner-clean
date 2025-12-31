<?php

namespace App\Filament\Resources\PromotionDirectoryResource\Pages;

use App\Filament\Resources\PromotionDirectoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPromotionDirectories extends ListRecords
{
    protected static string $resource = PromotionDirectoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
