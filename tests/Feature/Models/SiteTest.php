<?php

use App\Enums\SiteStatus;
use App\Models\Repository;
use App\Models\Site;

test('site belongs to repository', function () {
    $site = Site::factory()->create();

    expect($site->repository)->toBeInstanceOf(Repository::class);
});

test('repository has many sites', function () {
    $repository = Repository::factory()->create();
    Site::factory()->count(2)->create(['repository_id' => $repository->id]);

    expect($repository->sites)->toHaveCount(2);
});

test('site status is cast to enum', function () {
    $site = Site::factory()->create();

    expect($site->status)->toBeInstanceOf(SiteStatus::class);
});

test('can mark site as active', function () {
    $site = Site::factory()->create();

    $site->markAsActive('/home/ploi/test.marin.sh', '123456');

    expect($site->status)->toBe(SiteStatus::Active);
    expect($site->path)->toBe('/home/ploi/test.marin.sh');
    expect($site->ploi_site_id)->toBe('123456');
});

test('can mark site as failed', function () {
    $site = Site::factory()->create();

    $site->markAsFailed('Connection timeout');

    expect($site->status)->toBe(SiteStatus::Failed);
    expect($site->error_message)->toBe('Connection timeout');
});

test('isActive returns true only for active sites', function () {
    $active = Site::factory()->active()->create();
    $pending = Site::factory()->create();

    expect($active->isActive())->toBeTrue();
    expect($pending->isActive())->toBeFalse();
});

test('site can exist without repository', function () {
    $site = Site::factory()->create(['repository_id' => null]);

    expect($site->repository)->toBeNull();
    expect($site->exists)->toBeTrue();
});

test('site has branch field', function () {
    $site = Site::factory()->create(['branch' => 'feature-auth']);

    expect($site->branch)->toBe('feature-auth');
});

test('site has synced_from_ploi field', function () {
    $site = Site::factory()->create(['synced_from_ploi' => true]);

    expect($site->synced_from_ploi)->toBeTrue();
});
