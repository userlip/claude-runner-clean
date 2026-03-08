<?php

namespace App\Filament\Resources\Personas\Pages;

use App\Filament\Resources\Personas\PersonaResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePersona extends CreateRecord
{
    protected static string $resource = PersonaResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();

        return $data;
    }
}
