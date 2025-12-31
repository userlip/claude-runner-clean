<?php

use App\Jobs\CloneRepositoryJob;
use App\Jobs\RunApiHealthCheckJob;
use App\Models\AiProvider;
use App\Models\Repository;
use App\Models\ScrappApi;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Bus;

beforeEach(function () {
    User::factory()->create(['id' => 1]);
});

test('command returns failure when no active APIs exist', function () {
    Repository::factory()->create(['name' => 'scrappa']);
    AiProvider::factory()->glm()->create();

    $this->artisan('scrappa:health-check')
        ->expectsOutput('No active APIs found')
        ->assertExitCode(1);
});

test('command returns failure when repository not found', function () {
    ScrappApi::factory()->create();
    AiProvider::factory()->glm()->create();

    $this->artisan('scrappa:health-check')
        ->expectsOutput('Scrappa repository or GLM provider not found')
        ->assertExitCode(1);
});

test('command returns failure when GLM provider not found', function () {
    ScrappApi::factory()->create();
    Repository::factory()->create(['name' => 'scrappa']);

    $this->artisan('scrappa:health-check')
        ->expectsOutput('Scrappa repository or GLM provider not found')
        ->assertExitCode(1);
});

test('command creates a Task with correct attributes', function () {
    Bus::fake();

    $api = ScrappApi::factory()->create(['name' => 'Twitter API']);
    $repository = Repository::factory()->create(['name' => 'scrappa']);
    $glmProvider = AiProvider::factory()->glm()->create();

    $this->artisan('scrappa:health-check')
        ->assertExitCode(0);

    $task = Task::latest()->first();

    expect($task)->not->toBeNull();
    expect($task->user_id)->toBe(1);
    expect($task->repository_id)->toBe($repository->id);
    expect($task->ai_provider_id)->toBe($glmProvider->id);
    expect($task->scrapp_api_id)->toBe($api->id);
    expect($task->title)->toContain('Auto:');
    expect($task->title)->toContain('Twitter API');
    expect($task->workspace_path)->toStartWith('/home/ploi/workspaces/scrappa-');
});

test('command chains CloneRepositoryJob and RunApiHealthCheckJob', function () {
    Bus::fake();

    $api = ScrappApi::factory()->create(['name' => 'Instagram API']);
    Repository::factory()->create(['name' => 'scrappa']);
    AiProvider::factory()->glm()->create();

    $this->artisan('scrappa:health-check')
        ->assertExitCode(0);

    Bus::assertChained([
        CloneRepositoryJob::class,
        RunApiHealthCheckJob::class,
    ]);
});

test('command uses specific API when --api option provided', function () {
    Bus::fake();

    $inactiveApi = ScrappApi::factory()->inactive()->create(['name' => 'Inactive API']);
    $specificApi = ScrappApi::factory()->inactive()->create(['name' => 'Specific API']);
    Repository::factory()->create(['name' => 'scrappa']);
    AiProvider::factory()->glm()->create();

    $this->artisan('scrappa:health-check', ['--api' => $specificApi->id])
        ->assertExitCode(0);

    $task = Task::latest()->first();
    expect($task->scrapp_api_id)->toBe($specificApi->id);
    expect($task->title)->toContain('Specific API');
});

test('command picks random API when no --api option', function () {
    Bus::fake();

    $api1 = ScrappApi::factory()->create(['name' => 'API One']);
    $api2 = ScrappApi::factory()->create(['name' => 'API Two']);
    Repository::factory()->create(['name' => 'scrappa']);
    AiProvider::factory()->glm()->create();

    $this->artisan('scrappa:health-check')
        ->assertExitCode(0);

    $task = Task::latest()->first();
    expect([$api1->id, $api2->id])->toContain($task->scrapp_api_id);
});

test('command picks random skill from available skills', function () {
    Bus::fake();

    ScrappApi::factory()->create();
    Repository::factory()->create(['name' => 'scrappa']);
    AiProvider::factory()->glm()->create();

    $this->artisan('scrappa:health-check')
        ->assertExitCode(0);

    $task = Task::latest()->first();
    expect($task->title)->toMatch('/Auto: (scrappa-endpoint-testing|rapidapi-publishing) for/');
});

test('command outputs success message with API name and skill', function () {
    Bus::fake();

    $api = ScrappApi::factory()->create(['name' => 'YouTube API']);
    Repository::factory()->create(['name' => 'scrappa']);
    AiProvider::factory()->glm()->create();

    $this->artisan('scrappa:health-check')
        ->expectsOutputToContain('Started health check for YouTube API')
        ->assertExitCode(0);
});

test('command throws ModelNotFoundException for invalid --api option', function () {
    Repository::factory()->create(['name' => 'scrappa']);
    AiProvider::factory()->glm()->create();

    $this->artisan('scrappa:health-check', ['--api' => 999]);
})->throws(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

test('command ignores inactive APIs when picking random', function () {
    Bus::fake();

    ScrappApi::factory()->inactive()->create(['name' => 'Inactive API']);
    $activeApi = ScrappApi::factory()->create(['name' => 'Active API']);
    Repository::factory()->create(['name' => 'scrappa']);
    AiProvider::factory()->glm()->create();

    $this->artisan('scrappa:health-check')
        ->assertExitCode(0);

    $task = Task::latest()->first();
    expect($task->scrapp_api_id)->toBe($activeApi->id);
});
