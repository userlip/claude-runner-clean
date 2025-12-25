<?php

use App\Enums\SiteStatus;
use App\Jobs\ProvisionSiteJob;
use App\Models\Site;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

beforeEach(function () {
    config(['services.ploi.server_id' => '105384']);
});

test('job marks site as provisioning then active on success', function () {
    Process::fake([
        '*site:create*' => Process::result(
            output: 'Site created successfully. Site ID: 123456',
        ),
        '*repository:install*' => Process::result(
            output: 'Repository installed successfully',
        ),
    ]);

    $site = Site::factory()->create(['domain' => 'test-site.marin.sh']);

    expect($site->status)->toBe(SiteStatus::Pending);

    ProvisionSiteJob::dispatchSync($site);

    $site->refresh();

    expect($site->status)->toBe(SiteStatus::Active);
    expect($site->path)->toBe('/home/ploi/test-site.marin.sh');
    expect($site->ploi_site_id)->toBe('123456');
});

test('job sets path based on domain', function () {
    Process::fake([
        '*' => Process::result(output: 'Success'),
    ]);

    $site = Site::factory()->create(['domain' => 'my-project.marin.sh']);

    ProvisionSiteJob::dispatchSync($site);

    expect($site->fresh()->path)->toBe('/home/ploi/my-project.marin.sh');
});

test('job uses isolated user path when isolated_user is true', function () {
    Process::fake([
        '*' => Process::result(output: 'Success'),
    ]);

    $site = Site::factory()->create([
        'domain' => 'isolated-site.marin.sh',
        'isolated_user' => true,
    ]);

    ProvisionSiteJob::dispatchSync($site);

    expect($site->fresh()->path)->toBe('/home/isolated_site/isolated-site.marin.sh');
});

test('job marks site as failed on ploi error', function () {
    Process::fake([
        '*site:create*' => Process::result(
            exitCode: 1,
            errorOutput: 'Server connection failed',
        ),
    ]);

    $site = Site::factory()->create();

    expect(fn () => ProvisionSiteJob::dispatchSync($site))
        ->toThrow(RuntimeException::class);

    $site->refresh();

    expect($site->status)->toBe(SiteStatus::Failed);
    expect($site->error_message)->toContain('Failed to create site');
});

test('job creates database when database_name is set', function () {
    Process::fake([
        '*site:create*' => Process::result(output: 'Success'),
        '*repository:install*' => Process::result(output: 'Success'),
        '*database:create*' => Process::result(output: 'Database created'),
    ]);

    $site = Site::factory()->create(['database_name' => 'my_database']);

    ProvisionSiteJob::dispatchSync($site);

    Process::assertRan(fn (PendingProcess $process) => str_contains(implode(' ', $process->command), 'database:create')
        && str_contains(implode(' ', $process->command), 'my_database'));
});

test('job continues when repository installation fails', function () {
    Process::fake([
        '*site:create*' => Process::result(output: 'Site ID: 99999'),
        '*repository:install*' => Process::result(
            exitCode: 1,
            errorOutput: 'Repository not found',
        ),
    ]);

    Log::shouldReceive('info')->twice();
    Log::shouldReceive('debug')->twice();
    Log::shouldReceive('warning')->once();

    $site = Site::factory()->create();

    ProvisionSiteJob::dispatchSync($site);

    expect($site->fresh()->status)->toBe(SiteStatus::Active);
});

test('job extracts site id from various output formats', function () {
    $formats = [
        'Site ID: 123456' => '123456',
        'Created site 789012' => '789012',
        'site: 456789' => '456789',
    ];

    foreach ($formats as $output => $expectedId) {
        Process::fake([
            '*' => Process::result(output: $output),
        ]);

        $site = Site::factory()->create();

        ProvisionSiteJob::dispatchSync($site);

        expect($site->fresh()->ploi_site_id)->toBe($expectedId);
    }
});
