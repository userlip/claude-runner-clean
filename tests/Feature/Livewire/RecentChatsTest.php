<?php

use App\Livewire\RecentChats;
use App\Models\Repository;
use App\Models\Task;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('recent chat links use the app tasks show route', function () {
    $repository = Repository::factory()->create(['user_id' => $this->user->id]);
    $task = Task::factory()->create([
        'repository_id' => $repository->id,
        'title' => 'My Task',
    ]);

    Livewire::test(RecentChats::class)
        ->assertSeeHtml("/workbench/tasks/{$task->uuid}");
});

test('completed ralph chats use the completed status indicator in recent chats', function () {
    $repository = Repository::factory()->create(['user_id' => $this->user->id]);

    Task::factory()->completed()->create([
        'repository_id' => $repository->id,
        'title' => 'Completed Ralph Chat',
        'ralph_enabled' => true,
        'ralph_stopped_reason' => null,
    ]);

    Livewire::test(RecentChats::class)
        ->assertSee('Completed Ralph Chat')
        ->assertSeeHtml('bg-success')
        ->assertDontSeeHtml('bg-violet-500');
});

test('recent chats component root element has no wire:poll', function () {
    $component = Livewire::test(RecentChats::class);

    expect($component->html())->not->toContain('wire:poll');
});

test('recent-chats-updated event causes component to refresh', function () {
    $repository = Repository::factory()->create(['user_id' => $this->user->id]);

    $component = Livewire::test(RecentChats::class)
        ->assertDontSee('New Task After Event');

    Task::factory()->create([
        'repository_id' => $repository->id,
        'title' => 'New Task After Event',
    ]);

    $component->dispatch('recent-chats-updated')
        ->assertSuccessful()
        ->assertSee('New Task After Event');
});

test('app sidebar renders the recent chats component for authenticated users', function () {
    $this->get(route('workbench.home'))
        ->assertStatus(200)
        ->assertSeeLivewire(RecentChats::class);
});
