<?php

use TomatoPHP\FilamentPWA\Services\ManifestService;

it('defaults the pwa start_url to /admin', function () {
    $manifest = ManifestService::generate();
    $path = parse_url($manifest['start_url'] ?? '', PHP_URL_PATH);

    expect($path)->toBe('/admin');
});
