<?php

it('pwa settings page has been removed from the Filament admin panel', function () {
    expect(file_exists(app_path('Filament/Pages/PWASettingsPage.php')))->toBeFalse();
});
