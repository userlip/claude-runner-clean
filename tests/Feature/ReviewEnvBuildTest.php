<?php

use App\Services\PloiService;

it('replaces app url and db credentials in review env', function () {
    $env = "APP_URL=https://prod.example.com\nDB_USERNAME=prod\nDB_PASSWORD=prodpass\n";

    $ploi = new PloiService;
    $newEnv = $ploi->buildReviewEnv($env, 'https://review.example.com', 'ro_user', 'ro_pass');

    expect($newEnv)->toContain('APP_URL=https://review.example.com');
    expect($newEnv)->toContain('DB_USERNAME=ro_user');
    expect($newEnv)->toContain('DB_PASSWORD=ro_pass');
});
