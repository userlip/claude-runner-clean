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
        ->assertSeeHtml("/app/tasks/{$task->uuid}");
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
        ->assertSeeHtml('recent-chat-status-completed')
        ->assertDontSeeHtml('recent-chat-status-ralph');
});
