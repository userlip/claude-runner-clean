<?php

it('uses a constrained status bar style field instead of a color picker', function () {
    $src = file_get_contents(app_path('Filament/Pages/PWASettingsPage.php'));
    expect($src)->not->toContain("ColorPicker::make('pwa_status_bar')");
});
