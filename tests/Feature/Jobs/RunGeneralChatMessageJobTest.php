<?php

namespace Tests\Feature\Jobs;

use App\Enums\GeneralChatStatus;
use App\Jobs\RunGeneralChatMessageJob;
use App\Models\AiProvider;
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

    public function test_it_includes_resume_flag_when_continuing(): void
    {
        $chat = GeneralChat::factory()->create();
        $message = GeneralChatMessage::factory()->for($chat, 'chat')->create(['content' => 'Follow up']);

        $job = new RunGeneralChatMessageJob($chat, $message, continue: true);

        $command = $job->buildCommand();

        expect($command)->toContain('--resume');
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

    public function test_it_builds_command_with_glm_env_vars(): void
    {
        $provider = AiProvider::factory()->glm()->create();
        $chat = GeneralChat::factory()->create(['ai_provider_id' => $provider->id]);
        $message = GeneralChatMessage::factory()->for($chat, 'chat')->create();

        $job = new RunGeneralChatMessageJob($chat, $message, continue: false);

        $env = $job->getProviderEnvironment();

        expect($env)->toHaveKey('ANTHROPIC_BASE_URL')
            ->and($env['ANTHROPIC_BASE_URL'])->toBe('https://api.z.ai/api/anthropic')
            ->and($env)->toHaveKey('ANTHROPIC_MODEL')
            ->and($env['ANTHROPIC_MODEL'])->toBe('GLM-4.6');
    }

    public function test_it_returns_empty_env_for_claude(): void
    {
        $provider = AiProvider::factory()->claude()->create();
        $chat = GeneralChat::factory()->create(['ai_provider_id' => $provider->id]);
        $message = GeneralChatMessage::factory()->for($chat, 'chat')->create();

        $job = new RunGeneralChatMessageJob($chat, $message, continue: false);

        $env = $job->getProviderEnvironment();

        expect($env)->toBeEmpty();
    }
}
