<?php

use App\Models\Repository;
use App\Services\SecurityManagementService;
use Illuminate\Process\FakeProcessResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

it('updates deploy script and retries on untracked merge error', function () {
    config([
        'services.ploi.api_url' => 'https://ploi.io/api',
        'services.ploi.api_token' => 'test-token',
    ]);

    Http::fake([
        'https://ploi.io/api/*' => Http::sequence()
            ->push(['data' => ['deploy_script' => "git fetch origin\n"]], 200)
            ->push(['data' => []], 200),
    ]);

    Process::fake(function ($process) {
        static $deployCalls = 0;
        $command = is_array($process->command)
            ? implode(' ', $process->command)
            : $process->command;

        if (str_contains($command, 'ploi deploy')) {
            $deployCalls++;

            return $deployCalls === 1
                ? new FakeProcessResult('', 1, '', 'The following untracked working tree files would be overwritten by merge: public/build/assets/app.js')
                : new FakeProcessResult('', 0, 'Deployed', '');
        }

        return new FakeProcessResult('', 0, '', '');
    });

    $repo = Repository::factory()->create([
        'ploi_server_id' => '32593',
        'ploi_site_id' => '95778',
    ]);

    $service = app(SecurityManagementService::class);

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('deployRepository');
    $method->setAccessible(true);

    $method->invoke($service, $repo);

    Process::assertRanTimes(function ($process) {
        $command = is_array($process->command)
            ? implode(' ', $process->command)
            : $process->command;

        return str_contains($command, 'ploi deploy');
    }, 2);

    Http::assertSent(function ($request) {
        return $request->method() === 'PATCH'
            && str_contains($request->url(), '/servers/32593/sites/95778')
            && ($request['deploy_script'] ?? null) !== null;
    });
});
