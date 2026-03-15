<?php

use App\Models\Repository;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('uses gemini 3.1 flash lite preview as the default transcription model', function () {
    Storage::fake('local');

    config([
        'services.openrouter.api_key' => 'test-key',
    ]);

    Http::fake([
        'openrouter.ai/api/v1/chat/completions' => Http::response([
            'choices' => [
                ['message' => ['content' => 'hello world']],
            ],
        ], 200),
    ]);

    $task = Task::factory()->create(['user_id' => $this->user->id]);
    $audio = UploadedFile::fake()->create('voice.wav', 10, 'audio/wav');

    $token = 'test-csrf-token';
    $response = $this
        ->withSession(['_token' => $token])
        ->withHeader('X-CSRF-TOKEN', $token)
        ->postJson(route('api.tasks.voice-transcribe', $task), [
            'audio' => $audio,
        ]);

    $response->assertOk()->assertJsonPath('transcript', 'hello world');

    Http::assertSent(fn (\Illuminate\Http\Client\Request $request) => data_get($request->data(), 'model') === 'google/gemini-3.1-flash-lite-preview');
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

    $audio = UploadedFile::fake()->create('voice.wav', 10, 'audio/wav');

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

test('uses mimetype over filename extension when guessing audio format', function () {
    Storage::fake('local');

    config([
        'services.openrouter.api_key' => 'test-key',
        'services.openrouter.transcription_model' => 'google/gemini-2.5-flash-lite',
    ]);

    Http::fake(function (\Illuminate\Http\Client\Request $request) {
        $format = data_get($request->data(), 'messages.1.content.1.input_audio.format');
        expect($format)->toBe('wav');

        return Http::response([
            'choices' => [
                ['message' => ['content' => 'ok']],
            ],
        ], 200);
    });

    $task = Task::factory()->create(['user_id' => $this->user->id]);

    // The client might upload a Blob with a misleading filename extension. The backend must
    // prioritize the mimetype to avoid sending the wrong "format" to the provider.
    $audio = UploadedFile::fake()->create('voice-message.webm', 10, 'audio/wav');

    $token = 'test-csrf-token';
    $response = $this
        ->withSession(['_token' => $token])
        ->withHeader('X-CSRF-TOKEN', $token)
        ->postJson(route('api.tasks.voice-transcribe', $task), [
            'audio' => $audio,
        ]);

    $response->assertOk()->assertJsonPath('transcript', 'ok');
});

test('rejects mp4 uploads with a clear 422 and does not call provider', function () {
    Storage::fake('local');

    config([
        'services.openrouter.api_key' => 'test-key',
        'services.openrouter.transcription_model' => 'openai/gpt-audio-mini',
    ]);

    Http::preventStrayRequests();

    $task = Task::factory()->create(['user_id' => $this->user->id]);

    $audio = UploadedFile::fake()->create('voice-message.m4a', 10, 'audio/mp4');

    $token = 'test-csrf-token';
    $response = $this
        ->withSession(['_token' => $token])
        ->withHeader('X-CSRF-TOKEN', $token)
        ->postJson(route('api.tasks.voice-transcribe', $task), [
            'audio' => $audio,
        ]);

    $response->assertUnprocessable();
});

test('maps provider 400s to a 422 so clients do not see it as a gateway failure', function () {
    Storage::fake('local');

    config([
        'services.openrouter.api_key' => 'test-key',
        'services.openrouter.transcription_model' => 'openai/gpt-audio-mini',
    ]);

    Http::fake([
        'openrouter.ai/api/v1/chat/completions' => Http::response([
            'error' => [
                'message' => 'Provider returned error',
                'metadata' => [
                    'raw' => '{"error":{"message":"The data provided for \'input_audio\' is not of valid wav format.","type":"invalid_request_error","param":"messages.[0].content.[1].input_audio.data","code":"invalid_value"}}',
                ],
            ],
        ], 400),
    ]);

    $task = Task::factory()->create(['user_id' => $this->user->id]);

    $audio = UploadedFile::fake()->create('voice.wav', 10, 'audio/wav');

    $token = 'test-csrf-token';
    $response = $this
        ->withSession(['_token' => $token])
        ->withHeader('X-CSRF-TOKEN', $token)
        ->postJson(route('api.tasks.voice-transcribe', $task), [
            'audio' => $audio,
        ]);

    $response->assertUnprocessable();
});

test('extracts transcript from json-wrapped assistant content', function () {
    Storage::fake('local');

    config([
        'services.openrouter.api_key' => 'test-key',
        'services.openrouter.transcription_model' => 'openai/gpt-4o-audio-preview',
    ]);

    Http::fake([
        'openrouter.ai/api/v1/chat/completions' => Http::response([
            'choices' => [
                ['message' => ['content' => '{"result":"hello from json"}']],
            ],
        ], 200),
    ]);

    $task = Task::factory()->create(['user_id' => $this->user->id]);
    $audio = UploadedFile::fake()->create('voice.wav', 10, 'audio/wav');

    $token = 'test-csrf-token';
    $response = $this
        ->withSession(['_token' => $token])
        ->withHeader('X-CSRF-TOKEN', $token)
        ->postJson(route('api.tasks.voice-transcribe', $task), [
            'audio' => $audio,
        ]);

    $response->assertOk()->assertJsonPath('transcript', 'hello from json');
});

test('allows transcribing a task in a repository you own even if task user_id differs', function () {
    Storage::fake('local');

    config([
        'services.openrouter.api_key' => 'test-key',
        'services.openrouter.transcription_model' => 'openai/gpt-4o-audio-preview',
    ]);

    Http::fake([
        'openrouter.ai/api/v1/chat/completions' => Http::response([
            'choices' => [
                ['message' => ['content' => 'ok']],
            ],
        ], 200),
    ]);

    $repo = Repository::factory()->create(['user_id' => $this->user->id]);
    $otherUser = User::factory()->create();
    $task = Task::factory()->create([
        'repository_id' => $repo->id,
        'user_id' => $otherUser->id,
    ]);

    $audio = UploadedFile::fake()->create('voice.wav', 10, 'audio/wav');

    $token = 'test-csrf-token';
    $response = $this
        ->withSession(['_token' => $token])
        ->withHeader('X-CSRF-TOKEN', $token)
        ->postJson(route('api.tasks.voice-transcribe', $task), [
            'audio' => $audio,
        ]);

    $response->assertOk()->assertJsonPath('transcript', 'ok');
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
