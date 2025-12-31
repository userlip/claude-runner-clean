<?php

use App\Models\ScrappApi;
use App\Models\Task;

test('scrapp api can be created with factory', function () {
    $scrappApi = ScrappApi::factory()->create([
        'name' => 'Google Maps',
        'slug' => 'google-maps',
        'route_prefix' => 'api/google-maps',
        'rapidapi_slug' => 'google-maps-scraper',
    ]);

    expect($scrappApi)->toBeInstanceOf(ScrappApi::class);
    expect($scrappApi->name)->toBe('Google Maps');
    expect($scrappApi->slug)->toBe('google-maps');
    expect($scrappApi->route_prefix)->toBe('api/google-maps');
    expect($scrappApi->rapidapi_slug)->toBe('google-maps-scraper');
    expect($scrappApi->is_active)->toBeTrue();
});

test('scrapp api has tasks relationship', function () {
    $scrappApi = ScrappApi::factory()->create();
    Task::factory()->count(3)->create(['scrapp_api_id' => $scrappApi->id]);

    expect($scrappApi->tasks)->toHaveCount(3);
    expect($scrappApi->tasks->first())->toBeInstanceOf(Task::class);
});

test('scrapp api latest task returns the most recent task', function () {
    $scrappApi = ScrappApi::factory()->create();

    $oldTask = Task::factory()->create([
        'scrapp_api_id' => $scrappApi->id,
        'created_at' => now()->subDays(2),
    ]);

    $newerTask = Task::factory()->create([
        'scrapp_api_id' => $scrappApi->id,
        'created_at' => now()->subDay(),
    ]);

    $latestTask = Task::factory()->create([
        'scrapp_api_id' => $scrappApi->id,
        'created_at' => now(),
    ]);

    expect($scrappApi->latestTask->id)->toBe($latestTask->id);
});

test('task belongs to scrapp api', function () {
    $scrappApi = ScrappApi::factory()->create();
    $task = Task::factory()->create(['scrapp_api_id' => $scrappApi->id]);

    expect($task->scrappApi)->toBeInstanceOf(ScrappApi::class);
    expect($task->scrappApi->id)->toBe($scrappApi->id);
});

test('scrapp api casts is_active to boolean', function () {
    $scrappApi = ScrappApi::factory()->create(['is_active' => true]);

    expect($scrappApi->is_active)->toBeBool();
    expect($scrappApi->is_active)->toBeTrue();
});

test('scrapp api casts last_tested_at to datetime', function () {
    $now = now();
    $scrappApi = ScrappApi::factory()->create(['last_tested_at' => $now]);

    expect($scrappApi->last_tested_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
});

test('scrapp api factory inactive state works', function () {
    $scrappApi = ScrappApi::factory()->inactive()->create();

    expect($scrappApi->is_active)->toBeFalse();
});

test('scrapp api factory passed state works', function () {
    $scrappApi = ScrappApi::factory()->passed()->create();

    expect($scrappApi->last_test_result)->toBe('passed');
    expect($scrappApi->last_tested_at)->not->toBeNull();
});

test('scrapp api factory failed state works', function () {
    $scrappApi = ScrappApi::factory()->failed()->create();

    expect($scrappApi->last_test_result)->toBe('failed');
    expect($scrappApi->last_tested_at)->not->toBeNull();
});

test('scrapp api can have nullable rapidapi_slug', function () {
    $scrappApi = ScrappApi::factory()->create(['rapidapi_slug' => null]);

    expect($scrappApi->rapidapi_slug)->toBeNull();
});
