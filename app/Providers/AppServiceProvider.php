<?php

namespace App\Providers;

use App\Filament\Pages\PWASettingsPage;
use App\Models\GoogleAnalyticsConnection;
use App\Models\Message;
use App\Models\Persona;
use App\Models\SearchConsoleConnection;
use App\Observers\GoogleAnalyticsConnectionObserver;
use App\Observers\MessageObserver;
use App\Observers\PersonaObserver;
use App\Observers\SearchConsoleConnectionObserver;
use Illuminate\Support\ServiceProvider;
use TomatoPHP\FilamentSettingsHub\Facades\FilamentSettingsHub;
use TomatoPHP\FilamentSettingsHub\Services\Contracts\SettingHold;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register observers
        Message::observe(MessageObserver::class);
        GoogleAnalyticsConnection::observe(GoogleAnalyticsConnectionObserver::class);
        SearchConsoleConnection::observe(SearchConsoleConnectionObserver::class);
        Persona::observe(PersonaObserver::class);

        FilamentSettingsHub::register([
            SettingHold::make()
                ->label('filament-pwa::messages.settings.title')
                ->icon('heroicon-o-sparkles')
                ->page(PWASettingsPage::class)
                ->description('filament-pwa::messages.settings.description')
                ->group('filament-settings-hub::messages.group'),
        ]);
    }
}
