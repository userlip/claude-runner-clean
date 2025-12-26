<?php

namespace Tests\Feature\Livewire;

use App\Jobs\RunGeneralChatMessageJob;
use App\Livewire\GeneralChatBox;
use App\Models\GeneralChat;
use App\Models\GeneralChatMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class GeneralChatBoxTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renders_with_chat(): void
    {
        $chat = GeneralChat::factory()->create();

        Livewire::test(GeneralChatBox::class, ['chat' => $chat])
            ->assertStatus(200)
            ->assertSee('Start a conversation');
    }

    public function test_it_displays_existing_messages(): void
    {
        $chat = GeneralChat::factory()->create();
        GeneralChatMessage::factory()->for($chat, 'chat')->user()->create(['content' => 'Hello Claude']);
        GeneralChatMessage::factory()->for($chat, 'chat')->assistant()->create(['content' => 'Hello! How can I help?']);

        Livewire::test(GeneralChatBox::class, ['chat' => $chat])
            ->assertSee('Hello Claude')
            ->assertSee('Hello! How can I help?');
    }

    public function test_it_sends_message_and_dispatches_job(): void
    {
        Queue::fake();

        $chat = GeneralChat::factory()->create();

        Livewire::test(GeneralChatBox::class, ['chat' => $chat])
            ->set('prompt', 'Update my skills please')
            ->call('sendMessage')
            ->assertSet('prompt', '');

        $this->assertDatabaseHas('general_chat_messages', [
            'general_chat_id' => $chat->id,
            'content' => 'Update my skills please',
        ]);

        Queue::assertPushed(RunGeneralChatMessageJob::class);
    }

    public function test_it_shows_thinking_indicator_when_running(): void
    {
        $chat = GeneralChat::factory()->running()->create();

        Livewire::test(GeneralChatBox::class, ['chat' => $chat])
            ->assertSee('Claude is thinking...');
    }

    public function test_it_disables_input_when_running(): void
    {
        $chat = GeneralChat::factory()->running()->create();

        Livewire::test(GeneralChatBox::class, ['chat' => $chat])
            ->assertSeeHtml('disabled');
    }

    public function test_it_displays_chat_title_in_header(): void
    {
        $chat = GeneralChat::factory()->withTitle('Skills Update Chat')->create();

        Livewire::test(GeneralChatBox::class, ['chat' => $chat])
            ->assertSee('Skills Update Chat');
    }

    public function test_it_shows_general_chat_label_when_no_title(): void
    {
        $chat = GeneralChat::factory()->create(['title' => null]);

        Livewire::test(GeneralChatBox::class, ['chat' => $chat])
            ->assertSee('General Chat');
    }

    public function test_can_insert_snippet_into_prompt(): void
    {
        $chat = GeneralChat::factory()->create();

        Livewire::test(GeneralChatBox::class, ['chat' => $chat])
            ->dispatch('insert-snippet', content: 'Inserted snippet text')
            ->assertSet('prompt', 'Inserted snippet text');
    }

    public function test_appends_snippet_to_existing_prompt_with_newlines(): void
    {
        $chat = GeneralChat::factory()->create();

        Livewire::test(GeneralChatBox::class, ['chat' => $chat])
            ->set('prompt', 'Existing text')
            ->dispatch('insert-snippet', content: 'Inserted snippet')
            ->assertSet('prompt', "Existing text\n\nInserted snippet");
    }
}
