<?php

namespace App\Http\Controllers\Api;

use App\Enums\MessageRole;
use App\Enums\MessageStatus;
use App\Events\MessageCreated;
use App\Http\Controllers\Controller;
use App\Jobs\RunClaudeMessageJob;
use App\Models\Message;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskMessagesController extends Controller
{
    public function index(Request $request, Task $task): JsonResponse
    {
        $sinceId = $request->query('since');

        $query = $task->messages()->oldest();

        if ($sinceId) {
            $query->where('id', '>', $sinceId);
        }

        $messages = $query->get()->map(fn ($message) => [
            'id' => $message->id,
            'role' => $message->role->value,
            'status' => $message->status->value,
            'content' => $message->content,
            'content_blocks' => $message->content_blocks,
            'images' => $message->images,
            'tool_calls' => $message->tool_calls,
            'tokens_in' => $message->tokens_in,
            'tokens_out' => $message->tokens_out,
            'cost_usd' => $message->cost_usd,
            'created_at' => $message->created_at->toISOString(),
            'html' => $message->isFromAssistant() ? $message->getFirstTextBlockHtml() : null,
            'grouped_blocks' => $message->isFromAssistant() ? $message->getGroupedBlocks() : null,
        ]);

        return response()->json([
            'task' => [
                'uuid' => $task->uuid,
                'status' => $task->status->value,
                'is_compacting' => $task->is_compacting,
                'compaction_count' => $task->compaction_count,
            ],
            'messages' => $messages,
        ]);
    }

    public function store(Request $request, Task $task): JsonResponse
    {
        // Verify user owns this task
        if ($task->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'content' => 'required_without:images|nullable|string|max:100000',
            'images' => 'nullable|array|max:10',
            'images.*.data' => 'required_with:images|string',
            'images.*.name' => 'nullable|string|max:255',
        ]);

        $content = $validated['content'] ?? '';
        $images = $validated['images'] ?? null;

        // Determine if message should be queued (task is running)
        $isQueued = $task->isRunning();

        $message = Message::create([
            'task_id' => $task->id,
            'role' => MessageRole::User,
            'status' => $isQueued ? MessageStatus::Queued : MessageStatus::Sent,
            'content' => $content,
            'images' => ! empty($images) ? $images : null,
        ]);

        // Broadcast the new message
        broadcast(new MessageCreated($message))->toOthers();

        // Dispatch the job if not queued
        if (! $isQueued) {
            RunClaudeMessageJob::dispatch($task, $message, continue: $task->messages()->count() > 1);
        }

        return response()->json([
            'message' => [
                'id' => $message->id,
                'role' => $message->role->value,
                'status' => $message->status->value,
                'content' => $message->content,
                'images' => $message->images,
                'created_at' => $message->created_at->toISOString(),
            ],
            'queued' => $isQueued,
        ], 201);
    }
}
