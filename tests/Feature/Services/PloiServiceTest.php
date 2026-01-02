<?php

use App\Models\Repository;
use App\Models\Site;
use App\Services\PloiService;
use Illuminate\Support\Facades\Process;

beforeEach(function () {
    config([
        'services.ploi.server_id' => '105384',
        'services.ploi.server_name' => 'test-server',
    ]);
});

test('sync sites creates new sites from ploi', function () {
    Process::fake([
        '*site:list*' => Process::result(
            output: '+--------+--------+------------------------+--------------+---------------------+-------------+----------------+
| ID     | Server | Domain                 | Project type | Last deploy at      | PHP version | Has repository |
+--------+--------+------------------------+--------------+---------------------+-------------+----------------+
| 335361 | 105384 | test-site.marin.sh     | laravel      | 2025-12-25 10:57:31 | 8.4         | No             |
+--------+--------+------------------------+--------------+---------------------+-------------+----------------+',
        ),
    ]);

    $service = new PloiService;
    $count = $service->syncSites();

    expect($count)->toBe(1);
    expect(Site::where('domain', 'test-site.marin.sh')->exists())->toBeTrue();

    $site = Site::where('domain', 'test-site.marin.sh')->first();
    expect($site->synced_from_ploi)->toBeTrue();
    expect($site->ploi_site_id)->toBe('335361');
    expect($site->php_version)->toBe('8.4');
});

test('sync sites updates existing sites', function () {
    $site = Site::factory()->syncedFromPloi()->create([
        'domain' => 'existing.marin.sh',
        'ploi_site_id' => '123456',
        'php_version' => '8.3',
    ]);

    Process::fake([
        '*site:list*' => Process::result(
            output: '+--------+--------+------------------------+--------------+---------------------+-------------+----------------+
| ID     | Server | Domain                 | Project type | Last deploy at      | PHP version | Has repository |
+--------+--------+------------------------+--------------+---------------------+-------------+----------------+
| 123456 | 105384 | existing.marin.sh      | laravel      | 2025-12-25 10:57:31 | 8.4         | No             |
+--------+--------+------------------------+--------------+---------------------+-------------+----------------+',
        ),
    ]);

    $service = new PloiService;
    $service->syncSites();

    $site->refresh();
    expect($site->php_version)->toBe('8.4');
});

test('sync sites auto-matches repositories', function () {
    $repo = Repository::factory()->create([
        'full_name' => 'userlip/my-project',
    ]);

    Process::fake([
        '*site:list*' => Process::result(
            output: '+--------+--------+------------------------+--------------+---------------------+-------------+----------------+
| ID     | Server | Domain                 | Project type | Last deploy at      | PHP version | Has repository |
+--------+--------+------------------------+--------------+---------------------+-------------+----------------+
| 999999 | 105384 | my-project.marin.sh    | laravel      | 2025-12-25 10:57:31 | 8.4         | Yes            |
+--------+--------+------------------------+--------------+---------------------+-------------+----------------+',
        ),
        // Mock the repository info call
        '*' => Process::result(output: 'Repository: userlip/my-project'),
    ]);

    $service = new PloiService;
    $service->syncSites();

    $site = Site::where('domain', 'my-project.marin.sh')->first();
    expect($site->repository_id)->toBe($repo->id);
});
