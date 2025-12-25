<?php

use App\Enums\SiteStatus;
use App\Jobs\DeployToSiteJob;
use App\Models\Repository;
use App\Models\Site;
use App\Models\Task;
use Illuminate\Support\Facades\Process;

beforeEach(function () {
    config(['services.ploi.server_id' => '105384']);
});

test('job creates branch and pushes', function () {
    Process::fake();

    $repository = Repository::factory()->create();
    $task = Task::factory()->create([
        'repository_id' => $repository->id,
        'workspace_path' => '/home/ploi/workspaces/test-repo',
    ]);

    DeployToSiteJob::dispatchSync($task, 'my-feature');

    Process::assertRan(fn ($p) => str_contains(implode(' ', $p->command), 'git checkout -b my-feature'));
    Process::assertRan(fn ($p) => str_contains(implode(' ', $p->command), 'git push'));
});

test('job creates ploi site', function () {
    Process::fake([
        '*' => Process::result(output: 'Success'),
    ]);

    $repository = Repository::factory()->create();
    $task = Task::factory()->create([
        'repository_id' => $repository->id,
        'workspace_path' => '/home/ploi/workspaces/test-repo',
    ]);

    DeployToSiteJob::dispatchSync($task, 'my-feature');

    Process::assertRan(fn ($p) => str_contains(implode(' ', $p->command), 'site:create'));
});

test('job creates site record on success', function () {
    Process::fake([
        '*' => Process::result(output: 'Success'),
    ]);

    $repository = Repository::factory()->create();
    $task = Task::factory()->create([
        'repository_id' => $repository->id,
        'workspace_path' => '/home/ploi/workspaces/test-repo',
    ]);

    DeployToSiteJob::dispatchSync($task, 'my-feature');

    $site = Site::where('domain', 'my-feature.marin.sh')->first();
    expect($site)->not->toBeNull();
    expect($site->repository_id)->toBe($repository->id);
    expect($site->branch)->toBe('my-feature');
    expect($site->status)->toBe(SiteStatus::Active);
});
