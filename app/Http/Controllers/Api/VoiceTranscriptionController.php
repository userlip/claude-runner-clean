<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class VoiceTranscriptionController extends Controller
{
    public function store(Request $request, Task $task): JsonResponse
    {
        if ((int) $task->user_id !== (int) $request->user()->id) {
            abort(403);
        }

        $data = $request->validate([
            'audio' => [
                'required',
                'file',
                // 50MB max; actual practical limit is also governed by PHP upload limits.
                'max:51200',
                // Keep broad; we pass through as base64 and let the provider decide.
                'mimetypes:audio/webm,audio/ogg,audio/mp4,audio/mpeg,audio/wav,audio/x-wav,audio/aac',
            ],
        ]);

        $apiKey = (string) config('services.openrouter.api_key', '');
        $model = (string) config('services.openrouter.transcription_model', 'google/gemini-2.5-flash-lite');

        if ($apiKey === '') {
            throw ValidationException::withMessages([
                'openrouter' => 'OpenRouter API key is not configured.',
            ]);
        }

        $file = $data['audio'];
        $path = $file->store('tmp/voice', 'local');

        try {
            $bytes = Storage::disk('local')->get($path);
            $base64 = base64_encode($bytes);

            $format = $this->guessAudioFormat($file->getClientOriginalExtension(), $file->getMimeType());

            $response = Http::withToken($apiKey)
                ->withHeaders([
                    // OpenRouter recommends these for attribution; safe even if unset.
                    'HTTP-Referer' => (string) config('app.url'),
                    'X-Title' => (string) config('app.name'),
                ])
                ->timeout(120)
                ->post('https://openrouter.ai/api/v1/chat/completions', [
                    'model' => $model,
                    'temperature' => 0,
                    'max_tokens' => 2048,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You are a speech-to-text engine. Return only the transcript text, with punctuation.',
                        ],
                        [
                            'role' => 'user',
                            'content' => [
                                [
                                    'type' => 'text',
                                    'text' => 'Transcribe this audio. Output only the transcript.',
                                ],
                                [
                                    'type' => 'input_audio',
                                    'input_audio' => [
                                        'data' => $base64,
                                        'format' => $format,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ]);

            if (! $response->successful()) {
                return response()->json([
                    'message' => 'Transcription failed.',
                    'error' => $response->json(),
                ], 502);
            }

            $json = $response->json();
            $transcript = (string) data_get($json, 'choices.0.message.content', '');
            $transcript = trim($transcript);

            if ($transcript === '') {
                return response()->json([
                    'message' => 'Transcription returned empty text.',
                ], 502);
            }

            return response()->json([
                'transcript' => $transcript,
            ]);
        } finally {
            Storage::disk('local')->delete($path);
        }
    }

    private function guessAudioFormat(?string $extension, ?string $mime): string
    {
        $ext = strtolower((string) $extension);
        $mime = strtolower((string) $mime);

        if ($ext !== '') {
            // Normalize common audio extensions.
            if ($ext === 'm4a' || $ext === 'mp4') {
                return 'm4a';
            }

            return $ext;
        }

        if (str_contains($mime, 'mp4')) {
            return 'm4a';
        }
        if (str_contains($mime, 'mpeg')) {
            return 'mp3';
        }
        if (str_contains($mime, 'wav')) {
            return 'wav';
        }
        if (str_contains($mime, 'webm')) {
            return 'webm';
        }
        if (str_contains($mime, 'ogg')) {
            return 'ogg';
        }

        return 'wav';
    }
}
