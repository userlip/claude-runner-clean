<?php

namespace App\Filament\Resources\TaskSchedules\Pages;

use App\Filament\Resources\TaskSchedules\TaskScheduleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTaskSchedules extends ListRecords
{
    protected static string $resource = TaskScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
