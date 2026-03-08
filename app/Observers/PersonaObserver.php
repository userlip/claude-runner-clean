<?php

namespace App\Observers;

use App\Models\Persona;
use App\Services\PersonaStorageService;
use Illuminate\Support\Facades\Log;

class PersonaObserver
{
    public function __construct(private PersonaStorageService $storageService) {}

    public function created(Persona $persona): void
    {
        try {
            $this->storageService->initializeStorage($persona);
        } catch (\Throwable $e) {
            Log::error('Failed to initialize persona storage on create', [
                'persona_id' => $persona->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function updated(Persona $persona): void
    {
        if ($persona->isDirty('master_prompt')) {
            try {
                $this->storageService->syncPromptFile($persona);
            } catch (\Throwable $e) {
                Log::error('Failed to sync persona prompt on update', [
                    'persona_id' => $persona->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
