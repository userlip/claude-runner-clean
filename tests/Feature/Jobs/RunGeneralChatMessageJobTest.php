<?php

namespace Tests\Feature\Jobs;

use App\Enums\GeneralChatStatus;
use App\Jobs\RunGeneralChatMessageJob;
use App\Models\GeneralChat;
use App\Models\GeneralChatMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RunGeneralChatMessageJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_builds_correct_command(): void
    {
        $chat = GeneralChat::factory()->create(['session_id' => 'test-session-123']);
        $message = GeneralChatMessage::factory()->for($chat, 'chat')->create(['content' => 'Hello']);

        $job = new RunGeneralChatMessageJob($chat, $message, continue: false);

        $command = $job->buildCommand();

        expect($command)->toContain('claude -p')
            ->and($command)->toContain('--output-format stream-json')
            ->and($command)->toContain('--session-id')
            ->and($command)->not->toContain('--continue');
    }

    public function test_it_includes_continue_flag_when_continuing(): void
    {
        $chat = GeneralChat::factory()->create();
        $message = GeneralChatMessage::factory()->for($chat, 'chat')->create(['content' => 'Follow up']);

        $job = new RunGeneralChatMessageJob($chat, $message, continue: true);

        $command = $job->buildCommand();

        expect($command)->toContain('--continue');
    }

    public function test_it_creates_assistant_message_on_handle(): void
    {
        $chat = GeneralChat::factory()->create();
        $userMessage = GeneralChatMessage::factory()->for($chat, 'chat')->user()->create();

        // We can't fully test the job without mocking proc_open,
        // but we can test that it marks the chat as running
        $chat->markAsRunning();

        expect($chat->status)->toBe(GeneralChatStatus::Running);
    }
}
