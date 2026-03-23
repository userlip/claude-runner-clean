<?php

use App\Enums\TaskStatus;
use App\Livewire\AsanaBoard;
use App\Models\Connection;
use App\Models\Repository;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
});

// --- Access control ---

it('redirects unauthenticated users to login for asana board', function () {
    $this->get('/workbench/asana')->assertRedirect('/admin/login');
});

it('returns 200 for authenticated users on asana board', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/workbench/asana')->assertOk();
});

// --- Connection Status ---

it('shows no connection message when user has no asana connection', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(AsanaBoard::class)
        ->assertSuccessful()
        ->assertSee('No Asana Connection')
        ->assertSee('Connect your Asana account');
});

// --- Workspace Loading ---

it('loads workspaces when user has asana connection', function () {
    $user = User::factory()->create();

    Connection::factory()->asana()->create([
        'user_id' => $user->id,
        'credentials' => 'test_pat_token',
        'metadata' => ['default_workspace_id' => 'ws_123'],
    ]);

    Http::fake([
        'app.asana.com/api/1.0/workspaces' => Http::response([
            'data' => [
                ['gid' => 'ws_123', 'name' => 'My Workspace'],
                ['gid' => 'ws_456', 'name' => 'Another Workspace'],
            ],
        ], 200),
    ]);

    Livewire::actingAs($user)
        ->test(AsanaBoard::class)
        ->assertSuccessful()
        ->assertSet('workspaces', [
            ['gid' => 'ws_123', 'name' => 'My Workspace'],
            ['gid' => 'ws_456', 'name' => 'Another Workspace'],
        ])
        ->assertSet('selectedWorkspaceId', 'ws_123'); // Default workspace pre-selected
});

// --- Project Loading ---

it('loads projects when workspace is selected', function () {
    $user = User::factory()->create();

    Connection::factory()->asana()->create([
        'user_id' => $user->id,
        'credentials' => 'test_pat_token',
    ]);

    Http::fake([
        'app.asana.com/api/1.0/workspaces' => Http::response([
            'data' => [['gid' => 'ws_123', 'name' => 'My Workspace']],
        ], 200),
        'app.asana.com/api/1.0/projects*' => Http::response([
            'data' => [
                ['gid' => 'proj_123', 'name' => 'Project A'],
                ['gid' => 'proj_456', 'name' => 'Project B'],
            ],
        ], 200),
    ]);

    $component = Livewire::actingAs($user)
        ->test(AsanaBoard::class)
        ->set('selectedWorkspaceId', 'ws_123');

    $component->call('loadProjects');

    $component->assertSet('projects', [
        ['gid' => 'proj_123', 'name' => 'Project A'],
        ['gid' => 'proj_456', 'name' => 'Project B'],
    ]);
});

// --- Repository Loading ---

it('loads user repositories on mount', function () {
    $user = User::factory()->create();
    $repo1 = Repository::factory()->create(['user_id' => $user->id, 'name' => 'Repo A']);
    $repo2 = Repository::factory()->create(['user_id' => $user->id, 'name' => 'Repo B']);

    Connection::factory()->asana()->create([
        'user_id' => $user->id,
        'credentials' => 'test_pat_token',
    ]);

    Http::fake([
        'app.asana.com/api/1.0/workspaces' => Http::response(['data' => []], 200),
    ]);

    Livewire::actingAs($user)
        ->test(AsanaBoard::class)
        ->assertSet('repositories', [
            ['id' => $repo1->id, 'name' => 'Repo A'],
            ['id' => $repo2->id, 'name' => 'Repo B'],
        ]);
});

// --- Repository Linking ---

it('links repository to asana project', function () {
    $user = User::factory()->create();
    $repository = Repository::factory()->create([
        'user_id' => $user->id,
        'asana_project_id' => null,
    ]);

    Connection::factory()->asana()->create([
        'user_id' => $user->id,
        'credentials' => 'test_pat_token',
    ]);

    Http::fake([
        'app.asana.com/api/1.0/workspaces' => Http::response(['data' => []], 200),
    ]);

    Livewire::actingAs($user)
        ->test(AsanaBoard::class)
        ->set('selectedProjectId', 'proj_123')
        ->set('linkedRepositoryId', (string) $repository->id);

    expect($repository->fresh()->asana_project_id)->toBe('proj_123');
});

it('unlinks repository from asana project', function () {
    $user = User::factory()->create();
    $repository = Repository::factory()->create([
        'user_id' => $user->id,
        'asana_project_id' => 'proj_123',
        'asana_testing_section_id' => 'section_456',
    ]);

    Connection::factory()->asana()->create([
        'user_id' => $user->id,
        'credentials' => 'test_pat_token',
    ]);

    Http::fake([
        'app.asana.com/api/1.0/workspaces' => Http::response(['data' => []], 200),
    ]);

    Livewire::actingAs($user)
        ->test(AsanaBoard::class)
        ->set('selectedProjectId', 'proj_123')
        ->set('linkedRepositoryId', (string) $repository->id)
        ->set('testingSectionId', 'section_456')
        ->set('linkedRepositoryId', null);

    $repository->refresh();
    expect($repository->asana_project_id)->toBeNull()
        ->and($repository->asana_testing_section_id)->toBeNull();
});

it('updates testing section for linked repository', function () {
    $user = User::factory()->create();
    $repository = Repository::factory()->create([
        'user_id' => $user->id,
        'asana_project_id' => 'proj_123',
        'asana_testing_section_id' => null,
    ]);

    Connection::factory()->asana()->create([
        'user_id' => $user->id,
        'credentials' => 'test_pat_token',
    ]);

    Http::fake([
        'app.asana.com/api/1.0/workspaces' => Http::response(['data' => []], 200),
    ]);

    Livewire::actingAs($user)
        ->test(AsanaBoard::class)
        ->set('selectedProjectId', 'proj_123')
        ->set('linkedRepositoryId', (string) $repository->id)
        ->set('testingSectionId', 'section_789');

    expect($repository->fresh()->asana_testing_section_id)->toBe('section_789');
});

// --- Section Loading ---

it('loads sections and tasks when project is selected', function () {
    $user = User::factory()->create();

    Connection::factory()->asana()->create([
        'user_id' => $user->id,
        'credentials' => 'test_pat_token',
    ]);

    Http::fake([
        'app.asana.com/api/1.0/workspaces' => Http::response(['data' => []], 200),
        'app.asana.com/api/1.0/projects/proj_123/sections' => Http::response([
            'data' => [
                ['gid' => 'sec_1', 'name' => 'To Do'],
                ['gid' => 'sec_2', 'name' => 'Done'],
            ],
        ], 200),
        'app.asana.com/api/1.0/tasks*' => Http::response([
            'data' => [
                [
                    'gid' => 'task_1',
                    'name' => 'Test Task',
                    'completed' => false,
                    'section' => ['gid' => 'sec_1', 'name' => 'To Do'],
                ],
            ],
        ], 200),
    ]);

    Livewire::actingAs($user)
        ->test(AsanaBoard::class)
        ->set('selectedProjectId', 'proj_123')
        ->assertSet('sections', [
            'sec_1' => [
                'gid' => 'sec_1',
                'name' => 'To Do',
                'tasks' => [
                    [
                        'gid' => 'task_1',
                        'name' => 'Test Task',
                        'completed' => false,
                        'section' => ['gid' => 'sec_1', 'name' => 'To Do'],
                    ],
                ],
            ],
            'sec_2' => [
                'gid' => 'sec_2',
                'name' => 'Done',
                'tasks' => [],
            ],
        ]);
});

// --- Task Panel ---

it('opens task panel with task details', function () {
    $user = User::factory()->create();

    Connection::factory()->asana()->create([
        'user_id' => $user->id,
        'credentials' => 'test_pat_token',
    ]);

    Http::fake([
        'app.asana.com/api/1.0/workspaces' => Http::response(['data' => []], 200),
        'app.asana.com/api/1.0/tasks/task_123*' => Http::response([
            'data' => [
                'gid' => 'task_123',
                'name' => 'Detailed Task',
                'notes' => 'Task description here',
                'completed' => false,
                'assignee' => ['gid' => 'user_1', 'name' => 'John Doe'],
                'due_on' => '2026-03-30',
                'section' => ['gid' => 'sec_1', 'name' => 'To Do'],
            ],
        ], 200),
    ]);

    Livewire::actingAs($user)
        ->test(AsanaBoard::class)
        ->call('openTaskPanel', 'task_123')
        ->assertSet('showTaskPanel', true)
        ->assertSet('selectedTaskId', 'task_123')
        ->assertSet('selectedTask', [
            'gid' => 'task_123',
            'name' => 'Detailed Task',
            'notes' => 'Task description here',
            'completed' => false,
            'assignee' => ['gid' => 'user_1', 'name' => 'John Doe'],
            'due_on' => '2026-03-30',
            'section' => ['gid' => 'sec_1', 'name' => 'To Do'],
        ]);
});

it('closes task panel', function () {
    $user = User::factory()->create();

    Connection::factory()->asana()->create([
        'user_id' => $user->id,
        'credentials' => 'test_pat_token',
    ]);

    Http::fake([
        'app.asana.com/api/1.0/workspaces' => Http::response(['data' => []], 200),
    ]);

    Livewire::actingAs($user)
        ->test(AsanaBoard::class)
        ->set('showTaskPanel', true)
        ->set('selectedTaskId', 'task_123')
        ->set('selectedTask', ['gid' => 'task_123', 'name' => 'Test', 'completed' => false])
        ->call('closeTaskPanel')
        ->assertSet('showTaskPanel', false)
        ->assertSet('selectedTaskId', null)
        ->assertSet('selectedTask', null);
});

// --- Start Claude Runner Task ---

it('creates claude runner task from asana task with pre-filled data', function () {
    $user = User::factory()->create();
    $repository = Repository::factory()->create([
        'user_id' => $user->id,
        'asana_project_id' => 'proj_123',
    ]);

    Connection::factory()->asana()->create([
        'user_id' => $user->id,
        'credentials' => 'test_pat_token',
    ]);

    Http::fake([
        'app.asana.com/api/1.0/workspaces' => Http::response(['data' => []], 200),
    ]);

    Livewire::actingAs($user)
        ->test(AsanaBoard::class)
        ->set('linkedRepositoryId', (string) $repository->id)
        ->set('selectedTask', [
            'gid' => 'asana_task_123',
            'name' => 'Implement Feature X',
            'notes' => 'This is the task description',
            'completed' => false,
        ])
        ->call('startClaudeRunnerTask')
        ->assertRedirect(route('workbench.tasks.show', ['uuid' => Task::where('asana_task_id', 'asana_task_123')->first()->uuid]));

    $this->assertDatabaseHas(Task::class, [
        'user_id' => $user->id,
        'title' => 'Implement Feature X',
        'repository_id' => $repository->id,
        'asana_task_id' => 'asana_task_123',
        'status' => TaskStatus::Pending->value,
    ]);

    $task = Task::where('asana_task_id', 'asana_task_123')->first();
    expect($task->messages()->count())->toBe(1)
        ->and($task->messages()->first()->content)->toBe('This is the task description');
});

it('stores asana_task_id when creating claude runner task', function () {
    $user = User::factory()->create();
    $repository = Repository::factory()->create(['user_id' => $user->id]);

    Connection::factory()->asana()->create([
        'user_id' => $user->id,
        'credentials' => 'test_pat_token',
    ]);

    Http::fake([
        'app.asana.com/api/1.0/workspaces' => Http::response(['data' => []], 200),
    ]);

    Livewire::actingAs($user)
        ->test(AsanaBoard::class)
        ->set('linkedRepositoryId', (string) $repository->id)
        ->set('selectedTask', [
            'gid' => 'asana_task_456',
            'name' => 'Bug Fix',
            'notes' => '',
        ])
        ->call('startClaudeRunnerTask');

    $this->assertDatabaseHas(Task::class, [
        'asana_task_id' => 'asana_task_456',
    ]);
});

it('auto-sets repository_id from linked repo when creating task', function () {
    $user = User::factory()->create();
    $repository = Repository::factory()->create(['user_id' => $user->id]);

    Connection::factory()->asana()->create([
        'user_id' => $user->id,
        'credentials' => 'test_pat_token',
    ]);

    Http::fake([
        'app.asana.com/api/1.0/workspaces' => Http::response(['data' => []], 200),
    ]);

    Livewire::actingAs($user)
        ->test(AsanaBoard::class)
        ->set('linkedRepositoryId', (string) $repository->id)
        ->set('selectedTask', [
            'gid' => 'asana_task_789',
            'name' => 'Test Task',
            'notes' => '',
        ])
        ->call('startClaudeRunnerTask');

    $task = Task::where('asana_task_id', 'asana_task_789')->first();
    expect($task->repository_id)->toBe($repository->id);
});

it('shows error when creating task without linked repository', function () {
    $user = User::factory()->create();

    Connection::factory()->asana()->create([
        'user_id' => $user->id,
        'credentials' => 'test_pat_token',
    ]);

    Http::fake([
        'app.asana.com/api/1.0/workspaces' => Http::response(['data' => []], 200),
    ]);

    Livewire::actingAs($user)
        ->test(AsanaBoard::class)
        ->set('linkedRepositoryId', null)
        ->set('selectedTask', [
            'gid' => 'asana_task_123',
            'name' => 'Test Task',
            'notes' => '',
            'completed' => false,
        ])
        ->call('startClaudeRunnerTask');

    // No task should be created
    $this->assertDatabaseMissing(Task::class, [
        'asana_task_id' => 'asana_task_123',
    ]);
});

it('redirects to existing task if cr task already exists for asana task', function () {
    $user = User::factory()->create();
    $repository = Repository::factory()->create(['user_id' => $user->id]);

    $existingTask = Task::factory()->create([
        'user_id' => $user->id,
        'repository_id' => $repository->id,
        'asana_task_id' => 'asana_task_existing',
        'title' => 'Existing Task',
        'status' => TaskStatus::Pending,
    ]);

    Connection::factory()->asana()->create([
        'user_id' => $user->id,
        'credentials' => 'test_pat_token',
    ]);

    Http::fake([
        'app.asana.com/api/1.0/workspaces' => Http::response(['data' => []], 200),
    ]);

    Livewire::actingAs($user)
        ->test(AsanaBoard::class)
        ->set('linkedRepositoryId', (string) $repository->id)
        ->set('selectedTask', [
            'gid' => 'asana_task_existing',
            'name' => 'Some Name',
            'notes' => 'Some description',
            'completed' => false,
        ])
        ->call('startClaudeRunnerTask')
        ->assertRedirect(route('workbench.tasks.show', ['uuid' => $existingTask->uuid]));

    // Should not create duplicate task
    expect(Task::where('asana_task_id', 'asana_task_existing')->count())->toBe(1);
});

// --- Unlink Repository ---

it('unlinks repository via unlinkRepository method', function () {
    $user = User::factory()->create();
    $repository = Repository::factory()->create([
        'user_id' => $user->id,
        'asana_project_id' => 'proj_123',
        'asana_testing_section_id' => 'section_456',
    ]);

    Connection::factory()->asana()->create([
        'user_id' => $user->id,
        'credentials' => 'test_pat_token',
    ]);

    Http::fake([
        'app.asana.com/api/1.0/workspaces' => Http::response(['data' => []], 200),
    ]);

    Livewire::actingAs($user)
        ->test(AsanaBoard::class)
        ->set('linkedRepositoryId', (string) $repository->id)
        ->call('unlinkRepository')
        ->assertSet('linkedRepositoryId', null)
        ->assertSet('testingSectionId', null);

    $repository->refresh();
    expect($repository->asana_project_id)->toBeNull()
        ->and($repository->asana_testing_section_id)->toBeNull();
});

// --- hasAsanaConnection ---

it('returns true when user has asana connection', function () {
    $user = User::factory()->create();

    Connection::factory()->asana()->create([
        'user_id' => $user->id,
        'credentials' => 'test_pat_token',
    ]);

    Http::fake([
        'app.asana.com/api/1.0/workspaces' => Http::response(['data' => []], 200),
    ]);

    $component = Livewire::actingAs($user)
        ->test(AsanaBoard::class);

    expect($component->instance()->hasAsanaConnection())->toBeTrue();
});

it('returns false when user has no asana connection', function () {
    $user = User::factory()->create();

    Http::fake([
        'app.asana.com/api/1.0/workspaces' => Http::response(['data' => []], 200),
    ]);

    $component = Livewire::actingAs($user)
        ->test(AsanaBoard::class);

    expect($component->instance()->hasAsanaConnection())->toBeFalse();
});
