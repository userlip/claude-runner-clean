<?php

namespace App\Livewire\Tasks;

use App\Enums\TaskStatus;
use App\Models\AiProvider;
use App\Models\Repository;
use App\Models\Site;
use App\Models\Task;
use Illuminate\View\View;
use Livewire\Component;
use Mary\Traits\Toast;

class Form extends Component
{
    use Toast;

    public ?Task $task = null;

    public string $title = '';

    public string $status = 'pending';

    public ?int $repositoryId = null;

    public ?int $siteId = null;

    public ?int $aiProviderId = null;

    public function mount(?int $id = null): void
    {
        if ($id) {
            $this->task = Task::findOrFail($id);
            $this->title = $this->task->title ?? '';
            $this->status = $this->task->status?->value ?? 'pending';
            $this->repositoryId = $this->task->repository_id;
            $this->siteId = $this->task->site_id;
            $this->aiProviderId = $this->task->ai_provider_id;
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
    public function siteOptions(): array
    {
        return Site::query()
            ->orderBy('domain')
            ->get()
            ->map(fn ($s) => ['id' => $s->id, 'name' => $s->domain])
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
    public function statusOptions(): array
    {
        return collect(TaskStatus::cases())
            ->map(fn ($case) => ['id' => $case->value, 'name' => $case->label()])
            ->toArray();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'status' => ['required', 'string'],
            'repositoryId' => ['nullable', 'integer', 'exists:repositories,id'],
            'siteId' => ['nullable', 'integer', 'exists:sites,id'],
            'aiProviderId' => ['nullable', 'integer', 'exists:ai_providers,id'],
        ]);

        $data = [
            'title' => $validated['title'],
            'status' => $validated['status'],
            'repository_id' => $validated['repositoryId'],
            'site_id' => $validated['siteId'],
            'ai_provider_id' => $validated['aiProviderId'],
        ];

        if ($this->task) {
            $this->task->update($data);
            $this->success('Task updated.');
        } else {
            Task::create(array_merge($data, [
                'user_id' => auth()->id(),
            ]));
            $this->success('Task created.');
        }

        $this->redirect(route('app.tasks.index'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.tasks.form', [
            'repositoryOptions' => $this->repositoryOptions(),
            'siteOptions' => $this->siteOptions(),
            'aiProviderOptions' => $this->aiProviderOptions(),
            'statusOptions' => $this->statusOptions(),
        ]);
    }
}
