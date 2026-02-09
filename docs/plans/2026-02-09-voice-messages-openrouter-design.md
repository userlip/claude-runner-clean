# Voice Messages (OpenRouter Transcription) Design

**Scope:** Add “voice message” support to the task chat UI. User records audio (tap to start, tap to stop), server transcribes via OpenRouter using `google/gemini-2.5-flash-lite`, then the client auto-sends the transcript as a normal user message. The raw audio is deleted server-side after transcription.

## Goals

- Voice input from iOS PWA feels native and fast.
- No OpenRouter API key in the browser (server-side only).
- No long-term storage of audio; only the transcript becomes part of chat history.
- Works with existing message/queue logic (when the assistant is running, transcript queues).

## Non-Goals

- Playing back voice messages in chat history.
- Storing audio blobs permanently.
- Real-time streaming transcription.

## UX

- New mic button next to the existing attachment button.
- Tap once to start recording, tap again to stop.
- Maximum recording length: 5 minutes (auto-stops).
- While transcribing, show a spinner and disable “Send”.
- On success, insert transcript into prompt and auto-send.

## Frontend Architecture

- Recording uses `getUserMedia` + `MediaRecorder` with runtime MIME selection. Preference is iOS-friendly `audio/mp4`, with fallback to Opus (`audio/webm` / `audio/ogg`) if supported.
- If recording is not supported or permission is denied, fall back to an `<input type="file" accept="audio/*">` selection workflow.
- Upload audio via `fetch()` `FormData` to the server endpoint (authenticated session + CSRF token).

## Backend Architecture

- New authenticated API endpoint:
  - `POST /api/tasks/{task}/voice-transcribe`
  - Requires user ownership of `{task}`.
  - Validates the uploaded audio file (type + size).
  - Stores audio temporarily on the local disk under `tmp/voice/`.
  - Calls OpenRouter `POST https://openrouter.ai/api/v1/chat/completions` with a multimodal message containing `input_audio` (base64).
  - Deletes the temp file in a `finally` block.
  - Returns `{ transcript: string }`.

## Configuration

- `.env`:
  - `OPENROUTER_API_KEY`
  - `OPENROUTER_TRANSCRIPTION_MODEL` (default: `google/gemini-2.5-flash-lite`)
- `config/services.php` contains the OpenRouter config keys.

