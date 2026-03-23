<?php

namespace App\Livewire\Playbooks;

use App\Enums\ProposalType;
use App\Models\Playbook;
use Illuminate\View\View;
use Livewire\Component;
use Mary\Traits\Toast;

class Form extends Component
{
    use Toast;

    public ?Playbook $playbook = null;

    public string $name = '';

    public string $proposal_type = '';

    public string $project = '';

    public string $description = '';

    public string $prompt_template = '';

    public bool $is_active = true;

    public function mount(?int $id = null): void
    {
        if ($id) {
            $this->playbook = Playbook::findOrFail($id);
            $this->name = $this->playbook->name;
            $this->proposal_type = $this->playbook->proposal_type->value;
            $this->project = $this->playbook->project ?? '';
            $this->description = $this->playbook->description ?? '';
            $this->prompt_template = $this->playbook->prompt_template ?? '';
            $this->is_active = $this->playbook->is_active;
        } else {
            $this->proposal_type = ProposalType::Other->value;
        }
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'proposal_type' => ['required', 'string'],
            'project' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'prompt_template' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        if ($this->playbook) {
            $this->playbook->update($validated);
            $this->success('Playbook updated.');
        } else {
            Playbook::create($validated);
            $this->success('Playbook created.');
        }

        $this->redirect(route('workbench.playbooks.index'), navigate: true);
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    public function proposalTypeOptions(): array
    {
        return collect(ProposalType::cases())
            ->map(fn (ProposalType $t): array => ['id' => $t->value, 'name' => $t->label()])
            ->all();
    }

    public function render(): View
    {
        return view('livewire.playbooks.form', [
            'proposalTypeOptions' => $this->proposalTypeOptions(),
        ]);
    }
}
