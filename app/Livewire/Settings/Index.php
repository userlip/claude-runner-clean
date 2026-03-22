<?php

namespace App\Livewire\Settings;

use Illuminate\View\View;
use Livewire\Component;
use Mary\Traits\Toast;

class Index extends Component
{
    use Toast;

    public bool $showRecentChats = true;

    public int $recentChatsLimit = 10;

    /** @var array<string> */
    public array $sidebarPanels = [];

    public const AVAILABLE_PANELS = [
        'session-info' => ['title' => 'Session', 'icon' => 'o-information-circle'],
        'file-browser' => ['title' => 'Files', 'icon' => 'o-folder'],
        'snippet-browser' => ['title' => 'Snippets', 'icon' => 'o-code-bracket-square'],
        'todo-list' => ['title' => 'To-Do', 'icon' => 'o-clipboard-document-check'],
        'todo-mobile' => ['title' => 'Mobile', 'icon' => 'o-device-phone-mobile'],
        'ralph' => ['title' => 'Ralph', 'icon' => 'o-cpu-chip'],
    ];

    public function mount(): void
    {
        $this->showRecentChats = (bool) auth()->user()->setting('show_recent_chats', true);
        $this->recentChatsLimit = (int) auth()->user()->setting('recent_chats_limit', 10);
        $this->sidebarPanels = auth()->user()->setting('sidebar_panels', array_keys(self::AVAILABLE_PANELS));
    }

    public function updatedShowRecentChats(): void
    {
        auth()->user()->setSetting('show_recent_chats', $this->showRecentChats);

        $this->success('Settings saved.');
    }

    public function updatedRecentChatsLimit(): void
    {
        $this->recentChatsLimit = max(1, min(50, $this->recentChatsLimit));
        auth()->user()->setSetting('recent_chats_limit', $this->recentChatsLimit);

        $this->success('Settings saved.');
    }

    public function togglePanel(string $key): void
    {
        if (in_array($key, $this->sidebarPanels)) {
            $this->sidebarPanels = array_values(array_diff($this->sidebarPanels, [$key]));
        } elseif (count($this->sidebarPanels) < 3) {
            $this->sidebarPanels[] = $key;
        } else {
            $this->warning('Maximum 3 panels allowed.');

            return;
        }

        auth()->user()->setSetting('sidebar_panels', $this->sidebarPanels);
        $this->success('Sidebar updated.');
    }

    public function render(): View
    {
        return view('livewire.settings.index');
    }
}
