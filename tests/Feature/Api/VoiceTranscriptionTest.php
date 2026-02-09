<?php

use App\Models\Task;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('can transcribe a voice message and deletes temp audio', function () {
    Storage::fake('local');

    config([
        'services.openrouter.api_key' => 'test-key',
        'services.openrouter.transcription_model' => 'google/gemini-2.5-flash-lite',
    ]);

    Http::fake([
        'openrouter.ai/api/v1/chat/completions' => Http::response([
            'choices' => [
                ['message' => ['content' => 'hello world']],
            ],
        ], 200),
    ]);

    $task = Task::factory()->create(['user_id' => $this->user->id]);

    $audio = UploadedFile::fake()->create('voice.webm', 10, 'audio/webm');

    $token = 'test-csrf-token';
    $response = $this
        ->withSession(['_token' => $token])
        ->withHeader('X-CSRF-TOKEN', $token)
        ->postJson(route('api.tasks.voice-transcribe', $task), [
            'audio' => $audio,
        ]);

    $response->assertOk()->assertJsonPath('transcript', 'hello world');

    expect(Storage::disk('local')->allFiles('tmp/voice'))->toBe([]);
});

test('forbids transcribing a task you do not own', function () {
    Storage::fake('local');

    config([
        'services.openrouter.api_key' => 'test-key',
        'services.openrouter.transcription_model' => 'google/gemini-2.5-flash-lite',
    ]);

    Http::fake();

    $otherUser = User::factory()->create();
    $task = Task::factory()->create(['user_id' => $otherUser->id]);

    $audio = UploadedFile::fake()->create('voice.webm', 10, 'audio/webm');

    $token = 'test-csrf-token';
    $response = $this
        ->withSession(['_token' => $token])
        ->withHeader('X-CSRF-TOKEN', $token)
        ->postJson(route('api.tasks.voice-transcribe', $task), [
            'audio' => $audio,
        ]);

    $response->assertForbidden();
});

test('requires authentication', function () {
    $task = Task::factory()->create();
    $audio = UploadedFile::fake()->create('voice.webm', 10, 'audio/webm');

    $this->app['auth']->forgetGuards();

    $token = 'test-csrf-token';
    $response = $this
        ->withSession(['_token' => $token])
        ->withHeader('X-CSRF-TOKEN', $token)
        ->postJson(route('api.tasks.voice-transcribe', $task), [
            'audio' => $audio,
        ]);

    $response->assertUnauthorized();
});
