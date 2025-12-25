<?php

use App\Filament\Resources\GeneralChatResource\Pages\GeneralChatPage;
use App\Filament\Resources\GeneralChatResource\Pages\ListGeneralChats;
use App\Models\GeneralChat;
use App\Models\User;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('can list general chats', function () {
    $chats = GeneralChat::factory()->count(3)->for($this->user)->create();

    livewire(ListGeneralChats::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords($chats);
});

test('only shows user own chats', function () {
    $ownChat = GeneralChat::factory()->for($this->user)->create();
    $otherChat = GeneralChat::factory()->create();

    livewire(ListGeneralChats::class)
        ->assertCanSeeTableRecords([$ownChat])
        ->assertCanNotSeeTableRecords([$otherChat]);
});

test('can create new chat', function () {
    livewire(ListGeneralChats::class)
        ->callTableAction('create');

    $this->assertDatabaseHas('general_chats', [
        'user_id' => $this->user->id,
    ]);
});

test('can open chat page', function () {
    $chat = GeneralChat::factory()->for($this->user)->create();

    $this->get(GeneralChatPage::getUrl(['record' => $chat]))
        ->assertStatus(200);
});

test('chat page shows chat and file browser', function () {
    $chat = GeneralChat::factory()->for($this->user)->create();

    livewire(GeneralChatPage::class, ['record' => $chat->uuid])
        ->assertSuccessful()
        ->assertSeeHtml('general-chat-box')
        ->assertSeeHtml('file-browser');
});
