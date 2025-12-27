<?php

namespace App\Livewire;

use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Jeffgreco13\FilamentBreezy\Livewire\MyProfileComponent;

class PushNotificationSettings extends MyProfileComponent
{
    protected string $view = 'livewire.push-notification-settings';

    public static $sort = 35;

    public bool $enabled = false;

    public bool $browserSupported = false;

    public ?string $subscriptionEndpoint = null;

    public function mount(): void
    {
        $user = Filament::getCurrentOrDefaultPanel()->auth()->user();
        $this->enabled = $user->push_notifications_enabled ?? false;
    }

    public function toggleEnabled(): void
    {
        $user = Filament::getCurrentOrDefaultPanel()->auth()->user();
        $this->enabled = ! $this->enabled;
        $user->update(['push_notifications_enabled' => $this->enabled]);

        Notification::make()
            ->success()
            ->title($this->enabled ? 'Push notifications enabled' : 'Push notifications disabled')
            ->send();
    }

    public function subscriptionRegistered(string $endpoint): void
    {
        $this->subscriptionEndpoint = $endpoint;
    }

    public function subscriptionRemoved(): void
    {
        $this->subscriptionEndpoint = null;
    }
}
