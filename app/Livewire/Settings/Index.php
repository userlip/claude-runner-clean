<?php

namespace App\Livewire\Settings;

use Illuminate\View\View;
use Livewire\Component;
use Mary\Traits\Toast;

class Index extends Component
{
    use Toast;

    public bool $showRecentChats = true;

    public bool $pinRecentChats = true;

    public int $recentChatsLimit = 10;

    /** @var array<string> */
    public array $sidebarPanels = [];

    public bool $triggerPrPollingEnabled = true;

    public bool $triggerPrDetectionEnabled = true;

    public bool $triggerPrNudgeEnabled = true;

    public string $triggerPrNudgeTemplate = '';

    public string $triggerPrIgnoredChecks = '';

    public const DEFAULT_NUDGE_TEMPLATE = 'Please check the latest code review in the PR (if available) and check out the result of the test suite. Act if you see something wrong.';

    public const DEFAULT_IGNORED_CHECKS = 'claude';

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
        $this->pinRecentChats = (bool) auth()->user()->setting('pin_recent_chats', true);
        $this->recentChatsLimit = (int) auth()->user()->setting('recent_chats_limit', 10);
        $this->sidebarPanels = auth()->user()->setting('sidebar_panels', array_keys(self::AVAILABLE_PANELS));

        $this->triggerPrPollingEnabled = (bool) auth()->user()->setting('trigger_pr_polling_enabled', true);
        $this->triggerPrDetectionEnabled = (bool) auth()->user()->setting('trigger_pr_detection_enabled', true);
        $this->triggerPrNudgeEnabled = (bool) auth()->user()->setting('trigger_pr_nudge_enabled', true);
        $this->triggerPrNudgeTemplate = (string) auth()->user()->setting('trigger_pr_nudge_template', self::DEFAULT_NUDGE_TEMPLATE);
        $this->triggerPrIgnoredChecks = (string) auth()->user()->setting('trigger_pr_ignored_checks', self::DEFAULT_IGNORED_CHECKS);
    }

    public function updatedShowRecentChats(): void
    {
        auth()->user()->setSetting('show_recent_chats', $this->showRecentChats);

        $this->success('Settings saved.');
    }

    public function updatedPinRecentChats(): void
    {
        auth()->user()->setSetting('pin_recent_chats', $this->pinRecentChats);

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

    public function updatedTriggerPrPollingEnabled(): void
    {
        auth()->user()->setSetting('trigger_pr_polling_enabled', $this->triggerPrPollingEnabled);
        $this->success('Settings saved.');
    }

    public function updatedTriggerPrDetectionEnabled(): void
    {
        auth()->user()->setSetting('trigger_pr_detection_enabled', $this->triggerPrDetectionEnabled);
        $this->success('Settings saved.');
    }

    public function updatedTriggerPrNudgeEnabled(): void
    {
        auth()->user()->setSetting('trigger_pr_nudge_enabled', $this->triggerPrNudgeEnabled);
        $this->success('Settings saved.');
    }

    public function saveTriggerSettings(): void
    {
        auth()->user()->setSetting('trigger_pr_nudge_template', $this->triggerPrNudgeTemplate);
        auth()->user()->setSetting('trigger_pr_ignored_checks', $this->triggerPrIgnoredChecks);
        $this->success('Trigger settings saved.');
    }

    public function resetNudgeTemplate(): void
    {
        $this->triggerPrNudgeTemplate = self::DEFAULT_NUDGE_TEMPLATE;
        $this->triggerPrIgnoredChecks = self::DEFAULT_IGNORED_CHECKS;
        auth()->user()->setSetting('trigger_pr_nudge_template', $this->triggerPrNudgeTemplate);
        auth()->user()->setSetting('trigger_pr_ignored_checks', $this->triggerPrIgnoredChecks);
        $this->success('Settings reset to defaults.');
    }

    public function render(): View
    {
        return view('livewire.settings.index');
    }
}
