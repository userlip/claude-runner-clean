<?php

use App\Enums\ProposalStatus;
use App\Models\Persona;
use App\Models\Proposal;
use App\Models\Repository;
use App\Models\User;
use App\Services\ProposalExecutionService;
use App\Services\TelegramService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();

    $this->user = User::factory()->create();
    $this->repository = Repository::factory()->create(['user_id' => $this->user->id]);
    $this->persona = Persona::factory()->create([
        'user_id' => $this->user->id,
        'repository_id' => $this->repository->id,
        'name' => 'SEO Specialist',
    ]);

    config(['telegram.webhook_secret' => 'test-secret']);
    config(['telegram.admin_chat_id' => '12345']);
});

afterEach(function () {
    if (isset($this->persona)) {
        $path = $this->persona->getStoragePath();
        if (File::isDirectory($path)) {
            File::deleteDirectory($path);
        }
    }
});

test('persona proposal formats correctly for Telegram with persona name', function () {
    $proposal = Proposal::factory()->create([
        'persona_id' => $this->persona->id,
        'project' => 'test-project',
        'title' => 'Improve SEO rankings',
        'description' => 'Executive summary: analyze and improve SEO performance across the site.',
    ]);

    $formatted = $proposal->formatForTelegram();

    expect($formatted)->toContain('Persona Proposal');
    expect($formatted)->toContain('SEO Specialist');
    expect($formatted)->toContain('Improve SEO rankings');
    expect($formatted)->toContain('Executive summary: analyze and improve SEO performance across the site.');
    expect($formatted)->toContain('test-project');
});

test('non-persona proposal uses standard header without persona name', function () {
    $proposal = Proposal::factory()->create([
        'persona_id' => null,
        'project' => 'test-project',
        'title' => 'Fix bug',
    ]);

    $formatted = $proposal->formatForTelegram();

    expect($formatted)->not->toContain('Persona Proposal');
    expect($formatted)->not->toContain('Persona:');
    expect($formatted)->toContain('New Proposal');
});

test('approving a persona proposal via Telegram callback triggers the correct service flow', function () {
    $telegramService = Mockery::mock(TelegramService::class);
    $telegramService->shouldReceive('isFromAdmin')->with('12345')->andReturn(true);
    $telegramService->shouldReceive('answerCallbackQuery')->andReturn(true);
    $telegramService->shouldReceive('updateProposalMessage')->once();
    $telegramService->shouldReceive('sendPlainMessage')->zeroOrMoreTimes();
    $telegramService->shouldReceive('sendProposalNotification')->zeroOrMoreTimes();
    app()->instance(TelegramService::class, $telegramService);

    $proposal = Proposal::factory()->create([
        'persona_id' => $this->persona->id,
        'project' => 'test-project',
        'title' => 'Improve SEO rankings',
        'description' => 'Analyze SEO performance',
    ]);

    $response = $this->postJson('/api/telegram/webhook/any-secret', [
        'callback_query' => [
            'id' => 'callback-123',
            'message' => ['chat' => ['id' => 12345]],
            'data' => "approve:{$proposal->id}",
        ],
    ], ['X-Telegram-Bot-Api-Secret-Token' => 'test-secret']);

    $response->assertOk();
    $response->assertJson(['status' => 'approved']);

    $proposal->refresh();
    expect($proposal->status)->toBe(ProposalStatus::Approved);
    expect($proposal->approved_at)->not->toBeNull();

    // Proposal::approve() triggers ProposalExecutionService which dispatches jobs for persona proposals
    expect($proposal->executed_task_id)->not->toBeNull();
});

test('approving a non-persona proposal via Telegram callback also uses Proposal::approve()', function () {
    $telegramService = Mockery::mock(TelegramService::class);
    $telegramService->shouldReceive('isFromAdmin')->with('12345')->andReturn(true);
    $telegramService->shouldReceive('answerCallbackQuery')->andReturn(true);
    $telegramService->shouldReceive('updateProposalMessage')->once();
    $telegramService->shouldReceive('sendPlainMessage')->zeroOrMoreTimes();
    $telegramService->shouldReceive('sendProposalNotification')->zeroOrMoreTimes();
    app()->instance(TelegramService::class, $telegramService);

    $proposal = Proposal::factory()->create([
        'project' => 'test-project',
        'title' => 'Fix a bug',
    ]);

    $response = $this->postJson('/api/telegram/webhook/any-secret', [
        'callback_query' => [
            'id' => 'callback-456',
            'message' => ['chat' => ['id' => 12345]],
            'data' => "approve:{$proposal->id}",
        ],
    ], ['X-Telegram-Bot-Api-Secret-Token' => 'test-secret']);

    $response->assertOk();
    $response->assertJson(['status' => 'approved']);

    $proposal->refresh();
    expect($proposal->status)->toBe(ProposalStatus::Approved);
    expect($proposal->approved_at)->not->toBeNull();
    expect($proposal->decision_time_seconds)->not->toBeNull();
});

test('TelegramService sendPersonaCycleNotification sends start notification', function () {
    $service = Mockery::mock(TelegramService::class)->makePartial();
    $service->shouldReceive('sendPlainMessage')
        ->once()
        ->withArgs(function (string $text) {
            return str_contains($text, 'Persona Cycle Started')
                && str_contains($text, 'SEO Specialist')
                && str_contains($text, '🔄');
        })
        ->andReturnNull();

    $service->sendPersonaCycleNotification('SEO Specialist', 'started', 'Running analysis cycle #5');
});

test('TelegramService sendPersonaCycleNotification sends complete notification', function () {
    $service = Mockery::mock(TelegramService::class)->makePartial();
    $service->shouldReceive('sendPlainMessage')
        ->once()
        ->withArgs(function (string $text) {
            return str_contains($text, 'Persona Cycle Completed')
                && str_contains($text, 'SEO Specialist')
                && str_contains($text, '✅');
        })
        ->andReturnNull();

    $service->sendPersonaCycleNotification('SEO Specialist', 'completed', 'Proposal created: Improve meta tags');
});
