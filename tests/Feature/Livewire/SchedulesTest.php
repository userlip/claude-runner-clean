<?php

use App\Livewire\Schedules\Form;
use App\Livewire\Schedules\Index;
use App\Models\AiProvider;
use App\Models\Repository;
use App\Models\TaskSchedule;
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

it('redirects unauthenticated users to login for schedules index', function () {
    $this->get('/app/schedules')->assertRedirect('/admin/login');
});

it('returns 403 for non-admin users on schedules index', function () {
    $this->actingAs($this->user)->get('/app/schedules')->assertForbidden();
});

it('returns 200 for admin users on schedules index', function () {
    $this->actingAs($this->admin)->get('/app/schedules')->assertOk();
});

it('returns 403 for non-admin users on schedules create page', function () {
    $this->actingAs($this->user)->get('/app/schedules/create')->assertForbidden();
});

it('returns 403 for non-admin users on schedules edit page', function () {
    $schedule = TaskSchedule::factory()->create();
    $this->actingAs($this->user)->get("/app/schedules/{$schedule->id}/edit")->assertForbidden();
});

// --- List component ---

it('renders the schedules index with schedules table', function () {
    $schedule = TaskSchedule::factory()->create(['name' => 'My Test Schedule']);

    $this->actingAs($this->admin);

    Livewire::test(Index::class)
        ->assertSuccessful()
        ->assertSee('My Test Schedule');
});

it('filters schedules by search term', function () {
    $provider = AiProvider::factory()->create();
    TaskSchedule::factory()->create(['name' => 'Alpha Schedule', 'ai_provider_id' => $provider->id]);
    TaskSchedule::factory()->create(['name' => 'Beta Schedule', 'ai_provider_id' => $provider->id]);

    $this->actingAs($this->admin);

    Livewire::test(Index::class)
        ->set('search', 'Alpha')
        ->assertSee('Alpha Schedule')
        ->assertDontSee('Beta Schedule');
});

it('deletes a schedule', function () {
    $schedule = TaskSchedule::factory()->create();

    $this->actingAs($this->admin);

    Livewire::test(Index::class)
        ->call('delete', $schedule->id);

    $this->assertDatabaseMissing(TaskSchedule::class, ['id' => $schedule->id]);
});

it('toggles the active status of a schedule inline', function () {
    $schedule = TaskSchedule::factory()->create(['is_active' => true]);

    $this->actingAs($this->admin);

    Livewire::test(Index::class)
        ->call('toggleActive', $schedule->id);

    $this->assertDatabaseHas(TaskSchedule::class, [
        'id' => $schedule->id,
        'is_active' => false,
    ]);
});

it('toggles inactive schedule to active', function () {
    $schedule = TaskSchedule::factory()->create(['is_active' => false]);

    $this->actingAs($this->admin);

    Livewire::test(Index::class)
        ->call('toggleActive', $schedule->id);

    $this->assertDatabaseHas(TaskSchedule::class, [
        'id' => $schedule->id,
        'is_active' => true,
    ]);
});

// --- Create form ---

it('creates a new schedule', function () {
    $repository = Repository::factory()->create();

    $this->actingAs($this->admin);

    Livewire::test(Form::class)
        ->set('name', 'New Schedule')
        ->set('prompt', 'Do something useful')
        ->set('cronExpression', '0 9 * * *')
        ->set('repositoryId', $repository->id)
        ->call('save');

    $this->assertDatabaseHas(TaskSchedule::class, [
        'name' => 'New Schedule',
        'cron_expression' => '0 9 * * *',
        'repository_id' => $repository->id,
    ]);
});

it('validates required fields on schedule create', function () {
    $this->actingAs($this->admin);

    Livewire::test(Form::class)
        ->set('name', '')
        ->set('prompt', '')
        ->call('save')
        ->assertHasErrors(['name', 'prompt', 'repositoryId']);
});

// --- Edit form ---

it('loads existing schedule data in edit form', function () {
    $schedule = TaskSchedule::factory()->create([
        'name' => 'Existing Schedule',
        'cron_expression' => '0 12 * * *',
    ]);

    $this->actingAs($this->admin);

    Livewire::test(Form::class, ['id' => $schedule->id])
        ->assertSet('name', 'Existing Schedule')
        ->assertSet('cronExpression', '0 12 * * *');
});

it('updates an existing schedule', function () {
    $schedule = TaskSchedule::factory()->create(['name' => 'Old Schedule']);

    $this->actingAs($this->admin);

    Livewire::test(Form::class, ['id' => $schedule->id])
        ->set('name', 'Updated Schedule')
        ->call('save');

    $this->assertDatabaseHas(TaskSchedule::class, [
        'id' => $schedule->id,
        'name' => 'Updated Schedule',
    ]);
});
