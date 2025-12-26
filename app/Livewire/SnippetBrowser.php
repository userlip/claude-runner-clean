<?php

namespace App\Livewire;

use App\Models\Snippet;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

class SnippetBrowser extends Component
{
    /**
     * @return Collection<int, Snippet>
     */
    #[Computed]
    public function snippets(): Collection
    {
        return Snippet::where('user_id', Auth::id())
            ->orderBy('sort_order')
            ->get();
    }

    public function insertSnippet(int $snippetId): void
    {
        $snippet = Snippet::where('user_id', Auth::id())
            ->find($snippetId);

        if ($snippet) {
            $this->dispatch('insert-snippet', content: $snippet->content);
        }
    }

    public function render()
    {
        return view('livewire.snippet-browser');
    }
}
