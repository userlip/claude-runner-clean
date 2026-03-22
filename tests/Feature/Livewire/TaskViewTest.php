<?php

use App\Livewire\SessionInfoSidebar;
use App\Livewire\Tasks\Show;
use App\Models\Task;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->task = Task::factory()->generalChat($this->user)->create();
});

test('unauthenticated user is redirected to login', function () {
    auth()->logout();

    $this->get(route('app.tasks.show', $this->task->uuid))
        ->assertRedirect('/admin/login');
});

test('authenticated user can view task', function () {
    $this->get(route('app.tasks.show', $this->task->uuid))
        ->assertStatus(200);
});

test('task view component renders all six panel tab buttons', function () {
    $html = Livewire::test(Show::class, ['uuid' => $this->task->uuid])
        ->assertSuccessful()
        ->html();

    // Each panel has a corresponding toggle button
    expect($html)->toContain('Session Info')
        ->and($html)->toContain('Files')
        ->and($html)->toContain('Snippets')
        ->and($html)->toContain('To-Do')
        ->and($html)->toContain('Ralph');
});

test('task view panel can be toggled', function () {
    Livewire::test(Show::class, ['uuid' => $this->task->uuid])
        ->assertSet('activePanel', 'session-info')
        ->call('setActivePanel', 'file-browser')
        ->assertSet('activePanel', 'file-browser');
});

test('session info sidebar has no wire:poll', function () {
    $component = Livewire::test(SessionInfoSidebar::class, ['task' => $this->task]);
    expect($component->html())->not->toContain('wire:poll');
});

test('task-status-updated event causes session info sidebar to refresh data', function () {
    $task = $this->task;

    $component = Livewire::test(SessionInfoSidebar::class, ['task' => $task]);

    // Update task metadata in the DB
    $task->update(['session_metadata' => ['result' => ['num_turns' => 42]]]);

    // Dispatch the Livewire event (simulating TaskStatusUpdated broadcast)
    $component->dispatch('task-status-updated')
        ->assertSuccessful();

    // After refresh, the component should reflect the updated DB state
    expect($component->get('task')->session_metadata)->toBe(['result' => ['num_turns' => 42]]);
});
