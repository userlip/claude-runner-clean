<?php

namespace App\Livewire\Snippets;

use App\Models\Snippet;
use Illuminate\View\View;
use Livewire\Component;
use Mary\Traits\Toast;

class Form extends Component
{
    use Toast;

    public ?Snippet $snippet = null;

    public string $name = '';

    public string $content = '';

    public int $sortOrder = 0;

    public function mount(?int $id = null): void
    {
        if ($id) {
            $this->snippet = Snippet::findOrFail($id);
            $this->name = $this->snippet->name;
            $this->content = $this->snippet->content;
            $this->sortOrder = $this->snippet->sort_order;
        }
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'sortOrder' => ['required', 'integer', 'min:0'],
        ]);

        $data = [
            'name' => $validated['name'],
            'content' => $validated['content'],
            'sort_order' => $validated['sortOrder'],
        ];

        if ($this->snippet) {
            $this->snippet->update($data);
            $this->success('Snippet updated.');
        } else {
            Snippet::create(array_merge($data, ['user_id' => auth()->id()]));
            $this->success('Snippet created.');
        }

        $this->redirect(route('workbench.snippets.index'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.snippets.form');
    }
}
