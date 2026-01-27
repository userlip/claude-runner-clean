<?php

namespace App\Filament\Resources\TaskSchedules\Pages;

use App\Filament\Resources\TaskSchedules\TaskScheduleResource;
use Filament\Resources\Pages\ListRecords;

class ListTaskSchedules extends ListRecords
{
    protected static string $resource = TaskScheduleResource::class;
}
