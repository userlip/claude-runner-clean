<?php

namespace App\Providers;

use App\Filament\Pages\PWASettingsPage;
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
