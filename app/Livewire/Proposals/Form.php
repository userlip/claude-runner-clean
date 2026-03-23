<?php

namespace App\Livewire\Proposals;

use App\Enums\ProposalPriority;
use App\Enums\ProposalStatus;
use App\Enums\ProposalType;
use App\Models\Proposal;
use Illuminate\View\View;
use Livewire\Component;
use Mary\Traits\Toast;

class Form extends Component
{
    use Toast;

    public ?Proposal $proposal = null;

    public string $title = '';

    public string $description = '';

    public string $project = '';

    public string $priority = '';

    public string $status = '';

    public string $type = '';

    public function mount(?string $uuid = null): void
    {
        if ($uuid) {
            $this->proposal = Proposal::where('uuid', $uuid)->firstOrFail();
            $this->title = $this->proposal->title;
            $this->description = $this->proposal->description ?? '';
            $this->project = $this->proposal->project ?? '';
            $this->priority = $this->proposal->priority->value;
            $this->status = $this->proposal->status->value;
            $this->type = $this->proposal->type->value;
        } else {
            $this->priority = ProposalPriority::Medium->value;
            $this->status = ProposalStatus::Pending->value;
            $this->type = ProposalType::Other->value;
        }
    }

    public function save(): void
    {
        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'project' => ['nullable', 'string', 'max:255'],
            'priority' => ['required', 'string'],
            'status' => ['required', 'string'],
            'type' => ['required', 'string'],
        ]);

        if ($this->proposal) {
            $this->proposal->update($validated);
            $this->success('Proposal updated.');
        } else {
            Proposal::create($validated);
            $this->success('Proposal created.');
        }

        $this->redirect(route('workbench.proposals.index'), navigate: true);
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    public function priorityOptions(): array
    {
        return collect(ProposalPriority::cases())
            ->map(fn (ProposalPriority $p): array => ['id' => $p->value, 'name' => $p->label()])
            ->all();
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    public function statusOptions(): array
    {
        return collect(ProposalStatus::cases())
            ->map(fn (ProposalStatus $s): array => ['id' => $s->value, 'name' => $s->label()])
            ->all();
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    public function typeOptions(): array
    {
        return collect(ProposalType::cases())
            ->map(fn (ProposalType $t): array => ['id' => $t->value, 'name' => $t->label()])
            ->all();
    }

    public function render(): View
    {
        return view('livewire.proposals.form', [
            'priorityOptions' => $this->priorityOptions(),
            'statusOptions' => $this->statusOptions(),
            'typeOptions' => $this->typeOptions(),
        ]);
    }
}
