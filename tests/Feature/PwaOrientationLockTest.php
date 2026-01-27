<?php

test('pwa manifest locks orientation to portrait', function () {
    $path = public_path('site.webmanifest');

    expect($path)->toBeFile();

    $manifest = json_decode(file_get_contents($path), true);

    expect($manifest)->not->toBeNull();
    expect($manifest['orientation'] ?? null)->toBe('portrait');
});
