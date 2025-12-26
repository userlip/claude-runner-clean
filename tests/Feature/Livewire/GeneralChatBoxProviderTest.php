<?php

use App\Livewire\GeneralChatBox;
use App\Models\AiProvider;
use App\Models\GeneralChat;
use App\Models\User;
use Livewire\Livewire;

test('it shows provider selector', function () {
    $user = User::factory()->create();
    AiProvider::factory()->claude()->create();
    AiProvider::factory()->glm()->create(['is_active' => true]);
    $chat = GeneralChat::factory()->for($user)->create();

    Livewire::actingAs($user)
        ->test(GeneralChatBox::class, ['chat' => $chat])
        ->assertSee('Claude')
        ->assertSee('GLM');
});

test('it can change provider', function () {
    $user = User::factory()->create();
    $claude = AiProvider::factory()->claude()->create();
    $glm = AiProvider::factory()->glm()->create(['is_active' => true]);
    $chat = GeneralChat::factory()->for($user)->create(['ai_provider_id' => $claude->id]);

    Livewire::actingAs($user)
        ->test(GeneralChatBox::class, ['chat' => $chat])
        ->call('setProvider', $glm->id)
        ->assertSet('chat.ai_provider_id', $glm->id);

    expect($chat->fresh()->ai_provider_id)->toBe($glm->id);
});

test('it shows current provider badge', function () {
    $user = User::factory()->create();
    $glm = AiProvider::factory()->glm()->create(['is_active' => true]);
    $chat = GeneralChat::factory()->for($user)->create(['ai_provider_id' => $glm->id]);

    Livewire::actingAs($user)
        ->test(GeneralChatBox::class, ['chat' => $chat])
        ->assertSee('GLM (z.ai)');
});
