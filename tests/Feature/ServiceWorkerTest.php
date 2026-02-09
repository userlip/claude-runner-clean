<?php

it('ships a syntactically sane service worker file', function () {
    $path = public_path('serviceworker.js');
    expect(file_exists($path))->toBeTrue();

    $contents = file_get_contents($path);

    // Basic sanity checks (guards against malformed output).
    expect($contents)->toMatch('/addEventListener\\((["\'])fetch\\1/');
    expect($contents)->toContain('/offline/');
    expect($contents)->not->toContain("\n\"\n");
});
