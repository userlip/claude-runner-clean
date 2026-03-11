<?php

namespace App\Providers;

use App\Filament\Pages\PWASettingsPage;
use App\Models\GoogleAnalyticsConnection;
use App\Models\SearchConsoleConnection;
use App\Observers\GoogleAnalyticsConnectionObserver;
use App\Observers\SearchConsoleConnectionObserver;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
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
        Fieldset::configureUsing(fn (Fieldset $fieldset) => $fieldset->columnSpanFull());
        Grid::configureUsing(fn (Grid $grid) => $grid->columnSpanFull());
        Section::configureUsing(fn (Section $section) => $section->columnSpanFull());

        // Register observers
        GoogleAnalyticsConnection::observe(GoogleAnalyticsConnectionObserver::class);
        SearchConsoleConnection::observe(SearchConsoleConnectionObserver::class);

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
