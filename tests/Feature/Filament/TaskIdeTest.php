<?php

use App\Filament\Resources\Tasks\Pages\TaskIde;
use App\Models\Repository;
use App\Models\Site;
use App\Models\Task;
use App\Models\User;
use Filament\Facades\Filament;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('ide page requires authentication', function () {
    auth()->logout();

    $repository = Repository::factory()->create(['user_id' => $this->user->id]);
    $task = Task::factory()->create(['repository_id' => $repository->id]);

    $this->get(TaskIde::getUrl(['record' => $task]))
        ->assertRedirect();
});

test('ide page loads for authenticated user', function () {
    $repository = Repository::factory()->create(['user_id' => $this->user->id]);
    $task = Task::factory()->create(['repository_id' => $repository->id]);

    $this->get(TaskIde::getUrl(['record' => $task]))
        ->assertOk()
        ->assertSee('ide-proxy');
});

test('ide page loads for general chat task', function () {
    $task = Task::factory()->generalChat($this->user)->create();

    $this->get(TaskIde::getUrl(['record' => $task]))
        ->assertOk()
        ->assertSee('ide-proxy');
});

test('ide url includes workspace folder when set', function () {
    $repository = Repository::factory()->create(['user_id' => $this->user->id]);
    $task = Task::factory()->create([
        'repository_id' => $repository->id,
        'workspace_path' => '/home/ploi/workspaces/test-project',
    ]);

    $this->get(TaskIde::getUrl(['record' => $task]))
        ->assertOk()
        ->assertSee(urlencode('/home/ploi/workspaces/test-project'));
});

test('ide url uses site path when task has site', function () {
    $repository = Repository::factory()->create(['user_id' => $this->user->id]);
    $site = Site::factory()->active()->create([
        'repository_id' => $repository->id,
        'path' => '/home/ploi/sites/example.com',
    ]);
    $task = Task::factory()->create([
        'repository_id' => $repository->id,
        'site_id' => $site->id,
        'workspace_path' => null,
    ]);

    $this->get(TaskIde::getUrl(['record' => $task]))
        ->assertOk()
        ->assertSee(urlencode('/home/ploi/sites/example.com'));
});

test('ide url defaults to home directory when no workspace or site', function () {
    $task = Task::factory()->generalChat($this->user)->create();

    $this->get(TaskIde::getUrl(['record' => $task]))
        ->assertOk()
        ->assertSee(urlencode('/home/ploi'));
});
