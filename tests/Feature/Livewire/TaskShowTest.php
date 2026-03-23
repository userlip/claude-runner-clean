<?php

use App\Models\Repository;
use App\Models\Task;
use App\Models\User;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->repository = Repository::factory()->create(['user_id' => $this->owner->id]);
    $this->task = Task::factory()->create(['repository_id' => $this->repository->id]);
});

it('redirects unauthenticated users to login for task show', function () {
    $this->get("/workbench/tasks/{$this->task->uuid}")->assertRedirect();
});

it('returns 200 for task owner', function () {
    $this->actingAs($this->owner)
        ->get("/workbench/tasks/{$this->task->uuid}")
        ->assertOk();
});

it('returns 403 for non-owner', function () {
    $nonOwner = User::factory()->create();

    $this->actingAs($nonOwner)
        ->get("/workbench/tasks/{$this->task->uuid}")
        ->assertForbidden();
});

it('renders task chat inside the app layout', function () {
    $this->actingAs($this->owner)
        ->get("/workbench/tasks/{$this->task->uuid}")
        ->assertSeeLivewire(\App\Livewire\Tasks\Show::class);
});

it('owner can access general chat task by user_id', function () {
    $generalTask = Task::factory()->generalChat($this->owner)->create();

    $this->actingAs($this->owner)
        ->get("/workbench/tasks/{$generalTask->uuid}")
        ->assertOk();
});

it('non-owner cannot access general chat task', function () {
    $generalTask = Task::factory()->generalChat($this->owner)->create();
    $nonOwner = User::factory()->create();

    $this->actingAs($nonOwner)
        ->get("/workbench/tasks/{$generalTask->uuid}")
        ->assertForbidden();
});
