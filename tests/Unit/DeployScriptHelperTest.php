<?php

use App\Support\DeployScriptHelper;

it('inserts cleanup before first git command', function () {
    $script = "cd /var/www\n".
        "git fetch origin && git reset --hard origin/master\n".
        "composer install\n";

    $updated = DeployScriptHelper::ensureCleanup($script, 'rm -rf public/build');

    expect($updated)->toContain("rm -rf public/build\n");
    expect(strpos($updated, 'rm -rf public/build'))
        ->toBeLessThan(strpos($updated, 'git fetch'));
});

it('does not duplicate cleanup if already present', function () {
    $script = "rm -rf public/build\n".
        "git fetch origin\n";

    $updated = DeployScriptHelper::ensureCleanup($script, 'rm -rf public/build');

    expect(substr_count($updated, 'rm -rf public/build'))
        ->toBe(1);
});

it('prepends cleanup if no git command exists', function () {
    $script = "cd /var/www\ncomposer install\n";

    $updated = DeployScriptHelper::ensureCleanup($script, 'rm -rf public/build');

    expect(strpos($updated, 'rm -rf public/build'))
        ->toBe(0);
});
