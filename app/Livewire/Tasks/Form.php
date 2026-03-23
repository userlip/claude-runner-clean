<?php

namespace App\Livewire\Tasks;

use App\Enums\TaskStatus;
use App\Jobs\CloneRepositoryJob;
use App\Models\AiProvider;
use App\Models\Repository;
use App\Models\Site;
use App\Models\Task;
use Illuminate\Support\Str;
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

    public string $workLocation = 'workspace';

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

    public function updatedRepositoryId(): void
    {
        $this->workLocation = 'workspace';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function repositoryOptions(): array
    {
        return Repository::query()
            ->where('user_id', auth()->id())
            ->orderBy('name')
            ->get()
            ->map(fn ($r) => ['id' => $r->id, 'name' => $r->full_name ?: $r->name])
            ->toArray();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function workLocationOptions(): array
    {
        $options = [
            ['id' => 'workspace', 'name' => 'New Workspace (fresh clone)'],
        ];

        if ($this->repositoryId) {
            $sites = Site::where('repository_id', $this->repositoryId)
                ->where('status', 'active')
                ->get();

            foreach ($sites as $site) {
                $options[] = ['id' => 'site_'.$site->id, 'name' => 'Site: '.$site->domain];
            }
        }

        return $options;
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
        if ($this->task) {
            $this->saveExisting();
        } else {
            $this->createNew();
        }
    }

    protected function createNew(): void
    {
        $this->validate([
            'repositoryId' => ['required', 'integer', 'exists:repositories,id'],
            'workLocation' => ['required', 'string'],
        ]);

        $data = [
            'user_id' => auth()->id(),
            'repository_id' => $this->repositoryId,
            'ai_provider_id' => AiProvider::getDefault()?->id,
        ];

        if ($this->workLocation === 'workspace') {
            $repository = Repository::find($this->repositoryId);
            $data['workspace_path'] = '/home/ploi/workspaces/'.Str::slug($repository->name).'-'.Str::random(8);
            $data['site_id'] = null;
        } else {
            $siteId = (int) str_replace('site_', '', $this->workLocation);
            $data['site_id'] = $siteId;
            $data['workspace_path'] = null;
        }

        $task = Task::create($data);

        if ($task->workspace_path) {
            CloneRepositoryJob::dispatch($task);
        }

        $this->redirect(route('workbench.tasks.show', $task->uuid), navigate: true);
    }

    protected function saveExisting(): void
    {
        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'status' => ['required', 'string'],
            'repositoryId' => ['nullable', 'integer', 'exists:repositories,id'],
            'siteId' => ['nullable', 'integer', 'exists:sites,id'],
            'aiProviderId' => ['nullable', 'integer', 'exists:ai_providers,id'],
        ]);

        $this->task->update([
            'title' => $validated['title'],
            'status' => $validated['status'],
            'repository_id' => $validated['repositoryId'],
            'site_id' => $validated['siteId'],
            'ai_provider_id' => $validated['aiProviderId'],
        ]);

        $this->success('Task updated.');
        $this->redirect(route('workbench.tasks.index'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.tasks.form', [
            'repositoryOptions' => $this->repositoryOptions(),
            'siteOptions' => $this->siteOptions(),
            'aiProviderOptions' => $this->aiProviderOptions(),
            'statusOptions' => $this->statusOptions(),
            'workLocationOptions' => $this->workLocationOptions(),
        ]);
    }
}
