<?php

namespace App\Livewire\Personas;

use App\Enums\PersonaStatus;
use App\Models\Persona;
use App\Models\Proposal;
use App\Models\Repository;
use Illuminate\View\View;
use Livewire\Component;
use Mary\Traits\Toast;

class Form extends Component
{
    use Toast;

    public ?Persona $persona = null;

    public string $name = '';

    public string $description = '';

    public string $master_prompt = '';

    public string $mcp_guidance = '';

    public string $status = '';

    public bool $is_active = true;

    public ?int $repository_id = null;

    public function mount(?string $slug = null): void
    {
        if ($slug) {
            $this->persona = Persona::where('slug', $slug)->firstOrFail();
            $this->name = $this->persona->name;
            $this->description = $this->persona->description ?? '';
            $this->master_prompt = $this->persona->master_prompt ?? '';
            $this->mcp_guidance = $this->persona->mcp_guidance ?? '';
            $this->status = $this->persona->status->value;
            $this->is_active = $this->persona->is_active;
            $this->repository_id = $this->persona->repository_id;
        } else {
            $this->status = PersonaStatus::Active->value;
        }
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'master_prompt' => ['nullable', 'string'],
            'mcp_guidance' => ['nullable', 'string'],
            'status' => ['required', 'string'],
            'is_active' => ['boolean'],
            'repository_id' => ['required', 'integer', 'exists:repositories,id'],
        ]);

        if ($this->persona) {
            $this->persona->update($validated);
            $this->success('Persona updated.');
        } else {
            Persona::create(array_merge($validated, ['user_id' => auth()->id()]));
            $this->success('Persona created.');
        }

        $this->redirect(route('workbench.personas.index'), navigate: true);
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    public function statusOptions(): array
    {
        return collect(PersonaStatus::cases())
            ->map(fn (PersonaStatus $s): array => ['id' => $s->value, 'name' => $s->label()])
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    public function repositoryOptions(): array
    {
        return Repository::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Repository $r): array => ['id' => $r->id, 'name' => $r->name])
            ->all();
    }

    /**
     * @return array<int, Proposal>
     */
    public function personaProposals(): array
    {
        if (! $this->persona) {
            return [];
        }

        return $this->persona->proposals()->latest()->limit(20)->get()->all();
    }

    public function render(): View
    {
        return view('livewire.personas.form', [
            'statusOptions' => $this->statusOptions(),
            'repositoryOptions' => $this->repositoryOptions(),
            'personaProposals' => $this->personaProposals(),
        ]);
    }
}
