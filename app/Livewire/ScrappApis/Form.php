<?php

namespace App\Livewire\ScrappApis;

use App\Models\ScrappApi;
use Illuminate\View\View;
use Livewire\Component;
use Mary\Traits\Toast;

class Form extends Component
{
    use Toast;

    public ?ScrappApi $scrappApi = null;

    public string $name = '';

    public string $slug = '';

    public string $route_prefix = '';

    public string $rapidapi_slug = '';

    public bool $is_active = true;

    public string $notes = '';

    public function mount(?int $id = null): void
    {
        if ($id) {
            $this->scrappApi = ScrappApi::findOrFail($id);
            $this->name = $this->scrappApi->name;
            $this->slug = $this->scrappApi->slug;
            $this->route_prefix = $this->scrappApi->route_prefix ?? '';
            $this->rapidapi_slug = $this->scrappApi->rapidapi_slug ?? '';
            $this->is_active = $this->scrappApi->is_active;
            $this->notes = $this->scrappApi->notes ?? '';
        }
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255'],
            'route_prefix' => ['nullable', 'string', 'max:255'],
            'rapidapi_slug' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($this->scrappApi) {
            $this->scrappApi->update($validated);
            $this->success('API updated.');
        } else {
            ScrappApi::create($validated);
            $this->success('API created.');
        }

        $this->redirect(route('workbench.scrapp-apis.index'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.scrapp-apis.form');
    }
}
