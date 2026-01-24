<?php

use App\Services\PloiService;
use Illuminate\Support\Facades\Process;

it('creates a readonly db user via ploi cli', function () {
    Process::fake([
        '*' => Process::result('', '', 0),
    ]);

    $ploi = new PloiService;
    $result = $ploi->createReadonlyDbUser('1', 'db_name', 'ro_user', 'secret');

    expect($result)->toBeTrue();
    Process::assertRan(function ($process) {
        $command = is_array($process->command) ? implode(' ', $process->command) : $process->command;

        return str_contains($command, 'database:create-user')
            && str_contains($command, '--readonly');
    });
});
