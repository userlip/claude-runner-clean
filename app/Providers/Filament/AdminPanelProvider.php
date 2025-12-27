<?php

namespace App\Providers\Filament;

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
use TomatoPHP\FilamentPWA\FilamentPWAPlugin;
use TomatoPHP\FilamentSettingsHub\FilamentSettingsHubPlugin;
use TomatoPHP\FilamentTranslations\FilamentTranslationsPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->favicon(asset('favicon.ico'))
            ->login()
            ->registration()
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
                FilamentPWAPlugin::make()
                    ->allowPWASettings(false),
                FilamentSettingsHubPlugin::make()
                    ->allowShield(),
                FilamentTranslationsPlugin::make(),

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
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">'.
                    '<link rel="apple-touch-icon" sizes="180x180" href="'.asset('apple-touch-icon.png').'">'.
                    '<link rel="icon" type="image/png" sizes="32x32" href="'.asset('favicon-32x32.png').'">'.
                    '<link rel="icon" type="image/png" sizes="16x16" href="'.asset('favicon-16x16.png').'">'.
                    '<style>'.file_get_contents(resource_path('css/filament/chat.css')).'</style>',
            )
            ->renderHook(
                PanelsRenderHook::SIDEBAR_FOOTER,
                fn (): string => Blade::render('@livewire(\'recent-chats\')'),
            );
    }
}
