<?php

use App\Filament\Resources\Tasks\Pages\CreateTask;
use App\Filament\Resources\Tasks\Pages\ListTasks;
use App\Jobs\CloneRepositoryJob;
use App\Models\Repository;
use App\Models\Site;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('can view tasks list', function () {
    $repository = Repository::factory()->create(['user_id' => $this->user->id]);
    $task = Task::factory()->create(['repository_id' => $repository->id]);

    livewire(ListTasks::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$task]);
});

test('can create task with new workspace', function () {
    Queue::fake();

    $repository = Repository::factory()->create(['user_id' => $this->user->id]);

    livewire(CreateTask::class)
        ->fillForm([
            'repository_id' => $repository->id,
            'work_location' => 'workspace',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $task = Task::where('repository_id', $repository->id)->first();
    expect($task)->not->toBeNull();
    expect($task->workspace_path)->toContain('/home/ploi/workspaces/');
    expect($task->site_id)->toBeNull();

    Queue::assertPushed(CloneRepositoryJob::class);
});

test('can create task on existing site', function () {
    Queue::fake();

    $repository = Repository::factory()->create(['user_id' => $this->user->id]);
    $site = Site::factory()->active()->create(['repository_id' => $repository->id]);

    livewire(CreateTask::class)
        ->fillForm([
            'repository_id' => $repository->id,
            'work_location' => 'site_'.$site->id,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $task = Task::where('repository_id', $repository->id)->first();
    expect($task->site_id)->toBe($site->id);
    expect($task->workspace_path)->toBeNull();

    Queue::assertNotPushed(CloneRepositoryJob::class);
});
