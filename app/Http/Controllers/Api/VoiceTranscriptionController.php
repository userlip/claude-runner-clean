<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class VoiceTranscriptionController extends Controller
{
    public function store(Request $request, Task $task): JsonResponse
    {
        $requestId = (string) Str::uuid();

        /** @var User $user */
        $user = $request->user();

        if (! $this->userCanAccessTask($user, $task)) {
            return response()->json([
                'message' => 'Forbidden: you do not have access to this task.',
                'request_id' => $requestId,
            ], 403)->header('X-Voice-Request-Id', $requestId);
        }

        $data = $request->validate([
            'audio' => [
                'required',
                'file',
                // 50MB max; actual practical limit is also governed by PHP upload limits.
                'max:51200',
                // Keep broad; we pass through as base64 and let the provider decide.
                'mimetypes:audio/webm,video/webm,audio/ogg,video/ogg,audio/mp4,video/mp4,audio/x-m4a,audio/mpeg,audio/wav,audio/x-wav,audio/aac',
            ],
        ]);

        $apiKey = (string) config('services.openrouter.api_key', '');
        $model = (string) config('services.openrouter.transcription_model', 'openai/gpt-4o-audio-preview');

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
            $providerSupportedFormats = ['wav', 'mp3'];

            // OpenRouter's OpenAI audio-chat models only accept a narrow set of formats via `input_audio`.
            // If the browser records AAC-in-MP4 (common on iOS), we currently cannot transcode server-side.
            // Reject early so the client can fall back to a WAV recorder and avoid confusing 502s.
            if (! in_array($format, $providerSupportedFormats, true)) {
                return response()->json([
                    'message' => "Unsupported audio format '{$format}'. Please record again (WAV recommended).",
                    'request_id' => $requestId,
                ], 422)->header('X-Voice-Request-Id', $requestId);
            }

            try {
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
                                'content' => 'You are a speech-to-text engine. Return only the transcript text, with punctuation. Do not output JSON.',
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
            } catch (\Throwable $e) {
                Log::warning('voice_transcribe_exception', [
                    'request_id' => $requestId,
                    'task_uuid' => $task->uuid,
                    'user_id' => $user->id,
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);

                return response()->json([
                    'message' => 'Transcription failed while contacting the transcription provider.',
                    'request_id' => $requestId,
                ], 502)->header('X-Voice-Request-Id', $requestId);
            }

            if (! $response->successful()) {
                $error = $response->json();
                $message = $this->extractOpenRouterErrorMessage($response->status(), $error, $response->body());
                $status = (int) $response->status();
                $clientStatus = ($status >= 400 && $status < 500 && $status !== 429) ? 422 : 502;

                Log::warning('voice_transcribe_provider_error', [
                    'request_id' => $requestId,
                    'task_uuid' => $task->uuid,
                    'user_id' => $user->id,
                    'openrouter_status' => $response->status(),
                    'openrouter_model' => $model,
                    'audio_format' => $format,
                    'audio_mime' => (string) $file->getMimeType(),
                    'audio_bytes' => strlen($bytes),
                    // Log raw body when JSON decoding fails (helps diagnose Cloudflare/HTML error pages).
                    'openrouter_body_snip' => $error === null ? Str::limit($response->body(), 800) : null,
                ]);

                return response()->json([
                    'message' => $message,
                    'error' => $error,
                    'request_id' => $requestId,
                ], $clientStatus)->header('X-Voice-Request-Id', $requestId);
            }

            $json = $response->json();
            $content = (string) data_get($json, 'choices.0.message.content', '');
            $transcript = $this->extractTranscriptFromAssistantContent($content);

            if ($transcript === '') {
                return response()->json([
                    'message' => 'Transcription returned empty text.',
                    'request_id' => $requestId,
                ], 502)->header('X-Voice-Request-Id', $requestId);
            }

            return response()->json([
                'transcript' => $transcript,
                'request_id' => $requestId,
            ])->header('X-Voice-Request-Id', $requestId);
        } finally {
            Storage::disk('local')->delete($path);
        }
    }

    private function guessAudioFormat(?string $extension, ?string $mime): string
    {
        $ext = strtolower((string) $extension);
        $mime = strtolower((string) $mime);

        // Prefer mimetype. Clients can (and do) send a misleading filename extension,
        // especially when uploading a MediaRecorder Blob with a hardcoded name.
        if ($mime !== '') {
            if (str_contains($mime, 'mp4') || str_contains($mime, 'm4a')) {
                return 'm4a';
            }
            if (str_contains($mime, 'mpeg') || str_contains($mime, 'mp3')) {
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
            if (str_contains($mime, 'aac')) {
                return 'aac';
            }
        }

        if ($ext !== '') {
            // Normalize common audio extensions.
            if ($ext === 'm4a' || $ext === 'mp4') {
                return 'm4a';
            }

            return $ext;
        }

        return 'wav';
    }

    private function userCanAccessTask(User $user, Task $task): bool
    {
        $task->loadMissing([
            'repository:id,user_id',
            'site.repository:id,user_id',
        ]);

        $userId = (int) $user->id;

        if ((int) $task->user_id === $userId) {
            return true;
        }

        if ((int) ($task->repository?->user_id ?? 0) === $userId) {
            return true;
        }

        if ((int) ($task->site?->repository?->user_id ?? 0) === $userId) {
            return true;
        }

        return false;
    }

    private function extractTranscriptFromAssistantContent(string $content): string
    {
        $text = trim($content);

        // Some audio-capable models respond with a JSON object encoded as a string.
        // Extract common keys if present.
        $decoded = $this->tryDecodeJsonString($text);
        if (is_array($decoded)) {
            $candidate = data_get($decoded, 'transcript')
                ?? data_get($decoded, 'text')
                ?? data_get($decoded, 'result')
                ?? data_get($decoded, 'choices.0.message.content');

            if (is_string($candidate)) {
                return trim($candidate);
            }
        }

        return $text;
    }

    private function tryDecodeJsonString(string $text): ?array
    {
        $trimmed = trim($text);

        // Strip ```json fences if present.
        if (str_starts_with($trimmed, '```')) {
            $trimmed = preg_replace('/^```[a-zA-Z0-9_-]*\\s*/', '', $trimmed) ?? $trimmed;
            $trimmed = preg_replace('/\\s*```\\s*$/', '', $trimmed) ?? $trimmed;
            $trimmed = trim($trimmed);
        }

        if (! str_starts_with($trimmed, '{') || ! str_ends_with($trimmed, '}')) {
            return null;
        }

        try {
            /** @var array|null $decoded */
            $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function extractOpenRouterErrorMessage(int $status, mixed $errorJson, string $rawBody): string
    {
        $fallback = 'Transcription failed.';

        if (is_array($errorJson)) {
            $top = data_get($errorJson, 'error.message') ?? data_get($errorJson, 'message');
            $top = is_string($top) && $top !== '' ? $top : null;

            // OpenRouter sometimes wraps provider errors; the useful message is embedded in metadata.raw.
            $raw = data_get($errorJson, 'error.metadata.raw');
            if (is_string($raw) && $raw !== '') {
                $decoded = $this->tryDecodeJsonString($raw);
                $providerMsg = data_get($decoded, 'error.message') ?? data_get($decoded, 'message');
                if (is_string($providerMsg) && trim($providerMsg) !== '') {
                    return trim($providerMsg);
                }
            }

            if ($top !== null) {
                return trim($top);
            }
        }

        // If we got HTML, don't echo it back; just hint where it came from.
        $contentType = str_contains(strtolower($rawBody), '<!doctype html') ? 'text/html' : '';
        if ($contentType === 'text/html') {
            return "Transcription failed (provider returned HTML, HTTP {$status}).";
        }

        return $status ? "Transcription failed (HTTP {$status})." : $fallback;
    }
}
