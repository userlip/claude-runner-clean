<?php

namespace App\Livewire\PromotionDirectories;

use App\Enums\DirectoryCategory;
use App\Models\PromotionDirectory;
use Illuminate\View\View;
use Livewire\Component;
use Mary\Traits\Toast;

class Form extends Component
{
    use Toast;

    public ?PromotionDirectory $directory = null;

    public string $name = '';

    public string $url = '';

    public string $category = '';

    public string $submission_type = 'free';

    public string $submission_url = '';

    public string $notes = '';

    public function mount(?string $uuid = null): void
    {
        if ($uuid) {
            $this->directory = PromotionDirectory::where('uuid', $uuid)->firstOrFail();
            $this->name = $this->directory->name;
            $this->url = $this->directory->url;
            $this->category = $this->directory->category->value;
            $this->submission_type = $this->directory->submission_type;
            $this->submission_url = $this->directory->submission_url ?? '';
            $this->notes = $this->directory->notes ?? '';
        } else {
            $this->category = DirectoryCategory::Developer->value;
        }
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:500'],
            'category' => ['required', 'string'],
            'submission_type' => ['required', 'string', 'in:free,paid,invite_only'],
            'submission_url' => ['nullable', 'url', 'max:500'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($this->directory) {
            $this->directory->update($validated);
            $this->success('Directory updated.');
        } else {
            PromotionDirectory::create($validated);
            $this->success('Directory created.');
        }

        $this->redirect(route('app.promotion-directories.index'), navigate: true);
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    public function categoryOptions(): array
    {
        return collect(DirectoryCategory::cases())
            ->map(fn (DirectoryCategory $c): array => ['id' => $c->value, 'name' => $c->label()])
            ->all();
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    public function submissionTypeOptions(): array
    {
        return [
            ['id' => 'free', 'name' => 'Free'],
            ['id' => 'paid', 'name' => 'Paid'],
            ['id' => 'invite_only', 'name' => 'Invite Only'],
        ];
    }

    public function render(): View
    {
        return view('livewire.promotion-directories.form', [
            'categoryOptions' => $this->categoryOptions(),
            'submissionTypeOptions' => $this->submissionTypeOptions(),
        ]);
    }
}
