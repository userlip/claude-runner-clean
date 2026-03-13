<?php

namespace App\Observers;

use App\Models\Persona;
use App\Models\TaskSchedule;
use App\Services\PersonaStorageService;
use Illuminate\Support\Facades\Log;

class PersonaObserver
{
    public function __construct(private PersonaStorageService $storageService) {}

    public function created(Persona $persona): void
    {
        try {
            $this->storageService->initializeStorage($persona);
            $this->createDefaultSchedule($persona);
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

    private function createDefaultSchedule(Persona $persona): void
    {
        TaskSchedule::query()->firstOrCreate(
            ['persona_id' => $persona->id],
            [
                'repository_id' => $persona->repository_id,
                'user_id' => $persona->user_id,
                'ai_provider_id' => $persona->ai_provider_id,
                'name' => "{$persona->name} — Daily Analysis",
                'prompt' => "Run the scheduled analysis cycle for persona {$persona->name}.",
                'cron_expression' => config('personas.default_cron_expression', '0 8 * * *'),
                'is_active' => true,
            ]
        );
    }
}
