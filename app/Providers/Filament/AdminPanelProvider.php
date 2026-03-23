<?php

namespace App\Providers\Filament;

use App\Http\Middleware\DisableAdminCache;
use App\Livewire\PushNotificationSettings;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Jeffgreco13\FilamentBreezy\BreezyCore;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            // Enable Livewire SPA navigation for a more native PWA feel (fast transitions, back/forward).
            // Keep it disabled for guests so auth pages behave traditionally.
            ->spa(condition: fn (): bool => auth()->check(), hasPrefetching: true)
            ->favicon(asset('favicon.ico'))
            ->login()
            ->passwordReset()
            ->profile()
            ->sidebarCollapsibleOnDesktop()
            ->plugins([
                BreezyCore::make()
                    ->myProfile(
                        hasAvatars: true
                    )
                    ->myProfileComponents([
                        'push_notifications' => PushNotificationSettings::class,
                    ])
                    ->enableTwoFactorAuthentication(),
                FilamentShieldPlugin::make(),
            ])
            ->colors([
                'primary' => Color::Blue,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableAdminCache::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">'.
                    '<meta name="csrf-token" content="'.csrf_token().'">'.
                    '<link rel="apple-touch-icon" sizes="180x180" href="'.asset('apple-touch-icon.png').'">'.
                    '<link rel="icon" type="image/png" sizes="32x32" href="'.asset('favicon-32x32.png').'">'.
                    '<link rel="icon" type="image/png" sizes="16x16" href="'.asset('favicon-16x16.png').'">'.
                    Blade::render('@vite(\'resources/css/filament/admin.css\')').
                    $this->getSentryScript(),
            )
            ->renderHook(
                PanelsRenderHook::SCRIPTS_AFTER,
                fn (): string => Blade::render('@vite(\'resources/js/app.js\')'),
            );
    }

    protected function getSentryScript(): string
    {
        $dsn = config('sentry.dsn');
        if (empty($dsn)) {
            return '';
        }

        $environment = config('sentry.environment') ?: config('app.env');
        $release = config('sentry.release') ?: '';

        return '<script>'.
            'window.SENTRY_DSN = '.json_encode($dsn).';'.
            'window.SENTRY_ENVIRONMENT = '.json_encode($environment).';'.
            'window.SENTRY_RELEASE = '.json_encode($release).';'.
            '</script>';
    }
}
