<?php

namespace App\Livewire\Sites;

use App\Enums\SiteStatus;
use App\Models\Repository;
use App\Models\Site;
use Illuminate\View\View;
use Livewire\Component;
use Mary\Traits\Toast;

class Form extends Component
{
    use Toast;

    public ?Site $site = null;

    public string $domain = '';

    public string $branch = '';

    public string $phpVersion = '8.4';

    public string $webDirectory = '/public';

    public string $status = 'pending';

    public ?int $repositoryId = null;

    public function mount(?int $id = null): void
    {
        if ($id) {
            $this->site = Site::findOrFail($id);
            $this->domain = $this->site->domain ?? '';
            $this->branch = $this->site->branch ?? '';
            $this->phpVersion = $this->site->php_version ?? '8.4';
            $this->webDirectory = $this->site->web_directory ?? '/public';
            $this->status = $this->site->status?->value ?? 'pending';
            $this->repositoryId = $this->site->repository_id;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function repositoryOptions(): array
    {
        return Repository::query()
            ->orderBy('full_name')
            ->get()
            ->map(fn ($r) => ['id' => $r->id, 'name' => $r->full_name ?: $r->name])
            ->toArray();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function statusOptions(): array
    {
        return collect(SiteStatus::cases())
            ->map(fn ($case) => ['id' => $case->value, 'name' => $case->label()])
            ->toArray();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'domain' => ['required', 'string', 'max:255'],
            'branch' => ['nullable', 'string', 'max:255'],
            'phpVersion' => ['nullable', 'string', 'max:20'],
            'webDirectory' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'string'],
            'repositoryId' => ['nullable', 'integer', 'exists:repositories,id'],
        ]);

        $data = [
            'domain' => $validated['domain'],
            'branch' => $validated['branch'] ?: null,
            'php_version' => $validated['phpVersion'] ?: null,
            'web_directory' => $validated['webDirectory'] ?: null,
            'status' => $validated['status'],
            'repository_id' => $validated['repositoryId'],
        ];

        if ($this->site) {
            $this->site->update($data);
            $this->success('Site updated.');
        } else {
            Site::create($data);
            $this->success('Site created.');
        }

        $this->redirect(route('workbench.sites.index'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.sites.form', [
            'repositoryOptions' => $this->repositoryOptions(),
            'statusOptions' => $this->statusOptions(),
        ]);
    }
}
