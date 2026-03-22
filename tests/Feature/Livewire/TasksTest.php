<?php

use App\Livewire\Tasks\Form;
use App\Livewire\Tasks\Index;
use App\Models\Task;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->user = User::factory()->create();
});

// --- Access control ---

it('redirects unauthenticated users to login for tasks index', function () {
    $this->get('/app/tasks')->assertRedirect('/admin/login');
});

it('returns 403 for non-admin users on tasks index', function () {
    $this->actingAs($this->user)->get('/app/tasks')->assertForbidden();
});

it('returns 200 for admin users on tasks index', function () {
    $this->actingAs($this->admin)->get('/app/tasks')->assertOk();
});

it('returns 403 for non-admin users on tasks create page', function () {
    $this->actingAs($this->user)->get('/app/tasks/create')->assertForbidden();
});

it('returns 403 for non-admin users on tasks edit page', function () {
    $task = Task::factory()->generalChat($this->admin)->create();
    $this->actingAs($this->user)->get("/app/tasks/{$task->id}/edit")->assertForbidden();
});

// --- List component ---

it('renders the tasks index with tasks table', function () {
    $task = Task::factory()->generalChat($this->admin)->create(['title' => 'My Test Task']);

    $this->actingAs($this->admin);

    Livewire::test(Index::class)
        ->assertSuccessful()
        ->assertSee('My Test Task');
});

it('filters tasks by search term', function () {
    Task::factory()->generalChat($this->admin)->create(['title' => 'Alpha Task']);
    Task::factory()->generalChat($this->admin)->create(['title' => 'Beta Task']);

    $this->actingAs($this->admin);

    Livewire::test(Index::class)
        ->set('search', 'Alpha')
        ->assertSee('Alpha Task')
        ->assertDontSee('Beta Task');
});

it('deletes a task', function () {
    $task = Task::factory()->generalChat($this->admin)->create();

    $this->actingAs($this->admin);

    Livewire::test(Index::class)
        ->call('delete', $task->id);

    $this->assertDatabaseMissing(Task::class, ['id' => $task->id]);
});

// --- Create form ---

it('creates a new task', function () {
    $this->actingAs($this->admin);

    Livewire::test(Form::class)
        ->set('title', 'New Task Title')
        ->set('status', 'pending')
        ->call('save');

    $this->assertDatabaseHas(Task::class, [
        'title' => 'New Task Title',
        'status' => 'pending',
    ]);
});

it('validates required fields on task create', function () {
    $this->actingAs($this->admin);

    Livewire::test(Form::class)
        ->set('title', '')
        ->call('save')
        ->assertHasErrors(['title']);
});

// --- Edit form ---

it('loads existing task data in edit form', function () {
    $task = Task::factory()->generalChat($this->admin)->create(['title' => 'Existing Task']);

    $this->actingAs($this->admin);

    Livewire::test(Form::class, ['id' => $task->id])
        ->assertSet('title', 'Existing Task');
});

it('updates an existing task', function () {
    $task = Task::factory()->generalChat($this->admin)->create(['title' => 'Old Title']);

    $this->actingAs($this->admin);

    Livewire::test(Form::class, ['id' => $task->id])
        ->set('title', 'New Title')
        ->call('save');

    $this->assertDatabaseHas(Task::class, [
        'id' => $task->id,
        'title' => 'New Title',
    ]);
});
