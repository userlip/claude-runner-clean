<?php

use App\Enums\MessageRole;
use App\Enums\MessageStatus;
use App\Jobs\RunApiHealthCheckJob;
use App\Jobs\RunClaudeMessageJob;
use App\Models\Message;
use App\Models\ScrappApi;
use App\Models\Site;
use App\Models\Task;
use Illuminate\Support\Facades\Queue;

test('job creates message with correct prompt for scrappa-endpoint-testing', function () {
    Queue::fake();

    $site = Site::factory()->active()->create();
    $task = Task::factory()->create(['site_id' => $site->id]);
    $api = ScrappApi::factory()->create([
        'name' => 'Twitter Scraper',
        'route_prefix' => 'api/twitter',
    ]);

    $job = new RunApiHealthCheckJob($task, $api, 'scrappa-endpoint-testing');
    $job->handle();

    $message = Message::where('task_id', $task->id)->first();
    expect($message)->not->toBeNull();
    expect($message->role)->toBe(MessageRole::User);
    expect($message->status)->toBe(MessageStatus::Sent);
    expect($message->content)->toContain('Run /scrappa-endpoint-testing');
    expect($message->content)->toContain('Twitter Scraper');
    expect($message->content)->toContain('api/twitter');
    expect($message->content)->toContain('create a PR');
});

test('job creates message with correct prompt for rapidapi-publishing', function () {
    Queue::fake();

    $site = Site::factory()->active()->create();
    $task = Task::factory()->create(['site_id' => $site->id]);
    $api = ScrappApi::factory()->create([
        'name' => 'Instagram API',
        'rapidapi_slug' => 'instagram-scraper',
    ]);

    $job = new RunApiHealthCheckJob($task, $api, 'rapidapi-publishing');
    $job->handle();

    $message = Message::where('task_id', $task->id)->first();
    expect($message)->not->toBeNull();
    expect($message->role)->toBe(MessageRole::User);
    expect($message->status)->toBe(MessageStatus::Sent);
    expect($message->content)->toContain('Run /rapidapi-publishing');
    expect($message->content)->toContain('Instagram API');
    expect($message->content)->toContain('instagram-scraper');
    expect($message->content)->toContain('full autonomy');
});

test('job updates ScrappApi last_tested_at and last_test_result', function () {
    Queue::fake();

    $site = Site::factory()->active()->create();
    $task = Task::factory()->create(['site_id' => $site->id]);
    $api = ScrappApi::factory()->create([
        'last_tested_at' => null,
        'last_test_result' => null,
    ]);

    $job = new RunApiHealthCheckJob($task, $api, 'scrappa-endpoint-testing');
    $job->handle();

    $api->refresh();
    expect($api->last_tested_at)->not->toBeNull();
    expect($api->last_test_result)->toBe('running');
});

test('job dispatches RunClaudeMessageJob', function () {
    Queue::fake();

    $site = Site::factory()->active()->create();
    $task = Task::factory()->create(['site_id' => $site->id]);
    $api = ScrappApi::factory()->create();

    $job = new RunApiHealthCheckJob($task, $api, 'scrappa-endpoint-testing');
    $job->handle();

    Queue::assertPushed(RunClaudeMessageJob::class, function ($pushedJob) use ($task) {
        return $pushedJob->task->id === $task->id;
    });
});

test('job throws exception for unknown skill', function () {
    $site = Site::factory()->active()->create();
    $task = Task::factory()->create(['site_id' => $site->id]);
    $api = ScrappApi::factory()->create();

    $job = new RunApiHealthCheckJob($task, $api, 'unknown-skill');

    expect(fn () => $job->handle())->toThrow(InvalidArgumentException::class, 'Unknown skill: unknown-skill');
});

test('job has correct timeout', function () {
    $site = Site::factory()->active()->create();
    $task = Task::factory()->create(['site_id' => $site->id]);
    $api = ScrappApi::factory()->create();

    $job = new RunApiHealthCheckJob($task, $api, 'scrappa-endpoint-testing');

    expect($job->timeout)->toBe(300);
});

test('job has tries set to 1', function () {
    $site = Site::factory()->active()->create();
    $task = Task::factory()->create(['site_id' => $site->id]);
    $api = ScrappApi::factory()->create();

    $job = new RunApiHealthCheckJob($task, $api, 'scrappa-endpoint-testing');

    expect($job->tries)->toBe(1);
});

test('failed method updates last_test_result to failed', function () {
    $site = Site::factory()->active()->create();
    $task = Task::factory()->create(['site_id' => $site->id]);
    $api = ScrappApi::factory()->create([
        'last_test_result' => 'running',
    ]);

    $job = new RunApiHealthCheckJob($task, $api, 'scrappa-endpoint-testing');
    $job->failed(new \Exception('Test exception'));

    $api->refresh();
    expect($api->last_test_result)->toBe('failed');
});
