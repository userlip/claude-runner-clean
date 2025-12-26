<?php

use App\Enums\GeneralChatStatus;
use App\Models\AiProvider;
use App\Models\GeneralChat;
use App\Models\GeneralChatMessage;
use App\Models\User;

test('general chat generates uuid and session_id on create', function () {
    $chat = GeneralChat::factory()->create();

    expect($chat->uuid)->not->toBeNull()
        ->and($chat->session_id)->not->toBeNull();
});

test('general chat belongs to user', function () {
    $user = User::factory()->create();
    $chat = GeneralChat::factory()->for($user)->create();

    expect($chat->user->id)->toBe($user->id);
});

test('general chat has many messages', function () {
    $chat = GeneralChat::factory()->create();
    GeneralChatMessage::factory()->count(3)->for($chat, 'chat')->create();

    expect($chat->messages)->toHaveCount(3);
});

test('can mark general chat as running', function () {
    $chat = GeneralChat::factory()->create();

    $chat->markAsRunning();

    expect($chat->status)->toBe(GeneralChatStatus::Running)
        ->and($chat->started_at)->not->toBeNull();
});

test('can mark general chat as completed', function () {
    $chat = GeneralChat::factory()->running()->create();

    $chat->markAsCompleted();

    expect($chat->status)->toBe(GeneralChatStatus::Completed)
        ->and($chat->completed_at)->not->toBeNull();
});

test('general chat uses uuid as route key', function () {
    $chat = GeneralChat::factory()->create();

    expect($chat->getRouteKeyName())->toBe('uuid');
});

test('general chat default working directory is home', function () {
    $user = User::factory()->create();
    $chat = GeneralChat::create([
        'user_id' => $user->id,
    ]);

    expect($chat->working_directory)->toBe('/home/ploi');
});

test('it can check if running', function () {
    $pendingChat = GeneralChat::factory()->create();
    $runningChat = GeneralChat::factory()->running()->create();

    expect($pendingChat->isRunning())->toBeFalse()
        ->and($runningChat->isRunning())->toBeTrue();
});

test('it can mark as failed', function () {
    $chat = GeneralChat::factory()->running()->create();

    $chat->markAsFailed();

    expect($chat->status)->toBe(GeneralChatStatus::Failed)
        ->and($chat->completed_at)->not->toBeNull();
});

test('it belongs to ai provider', function () {
    $provider = AiProvider::factory()->create();
    $chat = GeneralChat::factory()->create(['ai_provider_id' => $provider->id]);

    expect($chat->aiProvider)->toBeInstanceOf(AiProvider::class)
        ->and($chat->aiProvider->id)->toBe($provider->id);
});

test('it uses default provider when none specified', function () {
    AiProvider::factory()->claude()->create();
    $chat = GeneralChat::factory()->create();

    expect($chat->aiProvider)->not->toBeNull()
        ->and($chat->aiProvider->name)->toBe('claude');
});
