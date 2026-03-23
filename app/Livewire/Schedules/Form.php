<?php

namespace App\Livewire\Schedules;

use App\Models\AiProvider;
use App\Models\Persona;
use App\Models\Repository;
use App\Models\TaskSchedule;
use Illuminate\View\View;
use Livewire\Component;
use Mary\Traits\Toast;

class Form extends Component
{
    use Toast;

    public ?TaskSchedule $schedule = null;

    public string $name = '';

    public string $prompt = '';

    public string $cronExpression = '0 * * * *';

    public bool $isActive = true;

    public ?int $repositoryId = null;

    public ?int $aiProviderId = null;

    public ?int $personaId = null;

    public ?int $deleteAfterMinutes = null;

    public function mount(?int $id = null): void
    {
        if ($id) {
            $this->schedule = TaskSchedule::findOrFail($id);
            $this->name = $this->schedule->name ?? '';
            $this->prompt = $this->schedule->prompt ?? '';
            $this->cronExpression = $this->schedule->cron_expression ?? '0 * * * *';
            $this->isActive = $this->schedule->is_active ?? true;
            $this->repositoryId = $this->schedule->repository_id;
            $this->aiProviderId = $this->schedule->ai_provider_id;
            $this->personaId = $this->schedule->persona_id;
            $this->deleteAfterMinutes = $this->schedule->delete_after_minutes;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function repositoryOptions(): array
    {
        return Repository::query()
            ->orderBy('name')
            ->get()
            ->map(fn ($r) => ['id' => $r->id, 'name' => $r->full_name ?: $r->name])
            ->toArray();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function aiProviderOptions(): array
    {
        return AiProvider::query()
            ->orderBy('name')
            ->get()
            ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])
            ->toArray();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function personaOptions(): array
    {
        return Persona::query()
            ->orderBy('name')
            ->get()
            ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])
            ->toArray();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'prompt' => ['required', 'string'],
            'cronExpression' => ['required', 'string', 'max:100'],
            'isActive' => ['boolean'],
            'repositoryId' => ['required', 'integer', 'exists:repositories,id'],
            'aiProviderId' => ['nullable', 'integer', 'exists:ai_providers,id'],
            'personaId' => ['nullable', 'integer', 'exists:personas,id'],
            'deleteAfterMinutes' => ['nullable', 'integer', 'min:1'],
        ]);

        $data = [
            'name' => $validated['name'],
            'prompt' => $validated['prompt'],
            'cron_expression' => $validated['cronExpression'],
            'is_active' => $validated['isActive'],
            'repository_id' => $validated['repositoryId'],
            'ai_provider_id' => $validated['aiProviderId'],
            'persona_id' => $validated['personaId'],
            'delete_after_minutes' => $validated['deleteAfterMinutes'],
        ];

        if ($this->schedule) {
            $this->schedule->update($data);
            $this->success('Schedule updated.');
        } else {
            TaskSchedule::create(array_merge($data, [
                'user_id' => auth()->id(),
            ]));
            $this->success('Schedule created.');
        }

        $this->redirect(route('workbench.schedules.index'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.schedules.form', [
            'repositoryOptions' => $this->repositoryOptions(),
            'aiProviderOptions' => $this->aiProviderOptions(),
            'personaOptions' => $this->personaOptions(),
        ]);
    }
}
