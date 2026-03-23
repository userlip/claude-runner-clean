<?php

namespace App\Providers;

use App\Models\Connection;
use App\Models\GoogleAnalyticsConnection;
use App\Models\Persona;
use App\Models\SearchConsoleConnection;
use App\Models\Task;
use App\Observers\ConnectionObserver;
use App\Observers\GoogleAnalyticsConnectionObserver;
use App\Observers\PersonaObserver;
use App\Observers\SearchConsoleConnectionObserver;
use App\Observers\TaskObserver;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Illuminate\Support\ServiceProvider;

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
        Connection::observe(ConnectionObserver::class);
        GoogleAnalyticsConnection::observe(GoogleAnalyticsConnectionObserver::class);
        SearchConsoleConnection::observe(SearchConsoleConnectionObserver::class);
        Persona::observe(PersonaObserver::class);
        Task::observe(TaskObserver::class);

    }
}
