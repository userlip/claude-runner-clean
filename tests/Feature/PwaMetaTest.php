<?php

use TomatoPHP\FilamentPWA\Services\ManifestService;

it('renders meta with a valid iOS status bar style', function () {
    $config = ManifestService::generate();

    // Simulate legacy/invalid value shape (previously stored as a hex color).
    $config['status_bar'] = '#000000';

    $html = view('filament-pwa::meta', ['config' => $config])->render();

    expect($html)->toContain('name="apple-mobile-web-app-status-bar-style"');
    expect($html)->toMatch('/content="(default|black|black-translucent)"/');
});
