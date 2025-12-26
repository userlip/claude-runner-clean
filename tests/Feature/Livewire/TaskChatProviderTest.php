<?php

use App\Livewire\TaskChat;
use App\Models\AiProvider;
use App\Models\Task;
use App\Models\User;
use Livewire\Livewire;

test('it shows provider selector', function () {
    $user = User::factory()->create();
    AiProvider::factory()->claude()->create();
    AiProvider::factory()->glm()->create(['is_active' => true]);
    $task = Task::factory()->create();

    Livewire::actingAs($user)
        ->test(TaskChat::class, ['task' => $task])
        ->assertSee('Claude')
        ->assertSee('GLM');
});

test('it can change provider', function () {
    $user = User::factory()->create();
    $claude = AiProvider::factory()->claude()->create();
    $glm = AiProvider::factory()->glm()->create(['is_active' => true]);
    $task = Task::factory()->create(['ai_provider_id' => $claude->id]);

    Livewire::actingAs($user)
        ->test(TaskChat::class, ['task' => $task])
        ->call('setProvider', $glm->id)
        ->assertSet('task.ai_provider_id', $glm->id);

    expect($task->fresh()->ai_provider_id)->toBe($glm->id);
});
