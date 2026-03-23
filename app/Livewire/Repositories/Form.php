<?php

namespace App\Livewire\Repositories;

use App\Enums\ValueTier;
use App\Models\Repository;
use Illuminate\View\View;
use Livewire\Component;
use Mary\Traits\Toast;

class Form extends Component
{
    use Toast;

    public ?Repository $repository = null;

    public string $name = '';

    public string $fullName = '';

    public string $description = '';

    public string $defaultBranch = 'main';

    public ?string $valueTier = null;

    public bool $isPrivate = false;

    public int $githubId = 0;

    public function mount(?int $id = null): void
    {
        if ($id) {
            $this->repository = Repository::findOrFail($id);
            $this->name = $this->repository->name ?? '';
            $this->fullName = $this->repository->full_name ?? '';
            $this->description = $this->repository->description ?? '';
            $this->defaultBranch = $this->repository->default_branch ?? 'main';
            $this->valueTier = $this->repository->value_tier?->value;
            $this->isPrivate = (bool) $this->repository->private;
            $this->githubId = $this->repository->github_id ?? 0;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function valueTierOptions(): array
    {
        return collect(ValueTier::cases())
            ->map(fn ($case) => ['id' => $case->value, 'name' => $case->getLabel()])
            ->toArray();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'fullName' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'defaultBranch' => ['nullable', 'string', 'max:255'],
            'valueTier' => ['nullable', 'string'],
            'isPrivate' => ['boolean'],
            'githubId' => ['integer', 'min:0'],
        ]);

        $fullName = $validated['fullName'] ?: $validated['name'];

        $data = [
            'name' => $validated['name'],
            'full_name' => $fullName,
            'clone_url' => "https://github.com/{$fullName}.git",
            'ssh_url' => "git@github.com:{$fullName}.git",
            'description' => $validated['description'] ?: null,
            'default_branch' => $validated['defaultBranch'] ?: 'main',
            'private' => $validated['isPrivate'],
            'github_id' => $validated['githubId'] ?: random_int(10000000, 99999999),
        ];

        // Only set value_tier if explicitly provided (otherwise DB uses default 'medium')
        if ($validated['valueTier']) {
            $data['value_tier'] = $validated['valueTier'];
        }

        if ($this->repository) {
            $this->repository->update($data);
            $this->success('Repository updated.');
        } else {
            Repository::create(array_merge($data, ['user_id' => auth()->id()]));
            $this->success('Repository created.');
        }

        $this->redirect(route('workbench.repositories.index'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.repositories.form', [
            'valueTierOptions' => $this->valueTierOptions(),
        ]);
    }
}
