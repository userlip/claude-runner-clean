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
use Illuminate\Support\Facades\Gate;

class ChatController extends Controller
{
    /**
     * Get messages for a task.
     */
    public function messages(Request $request, Task $task): JsonResponse
    {
        Gate::authorize('view', $task);

        $limit = $request->integer('limit', 50);
        $before = $request->input('before');

        $query = $task->messages()
            ->where('status', MessageStatus::Sent)
            ->oldest();

        if ($before) {
            $query->where('id', '<', $before);
        }

        $messages = $query->take($limit)->get();

        return response()->json([
            'messages' => $messages->map(fn (Message $message) => $this->formatMessage($message)),
            'has_more' => $task->messages()
                ->where('status', MessageStatus::Sent)
                ->where('id', '<', $messages->first()?->id ?? PHP_INT_MAX)
                ->exists(),
        ]);
    }

    /**
     * Send a message in a task chat.
     */
    public function sendMessage(Request $request, Task $task): JsonResponse
    {
        Gate::authorize('update', $task);

        $validated = $request->validate([
            'prompt' => 'nullable|string',
            'images' => 'array|max:10',
            'images.*.data' => 'required|string',
            'images.*.name' => 'required|string|max:255',
        ]);

        $hasContent = ! empty(trim($validated['prompt'] ?? '')) || ! empty($validated['images'] ?? []);

        if (! $hasContent) {
            return response()->json(['error' => 'Message content required'], 422);
        }

        $task->refresh();

        // If Claude is running, queue the message
        if ($task->isRunning()) {
            $message = Message::create([
                'task_id' => $task->id,
                'role' => MessageRole::User,
                'status' => MessageStatus::Queued,
                'content' => $validated['prompt'] ?? '',
                'images' => $validated['images'] ?? null,
            ]);

            return response()->json([
                'message' => $this->formatMessage($message),
                'queued' => true,
            ]);
        }

        // Check if there's a successful assistant response to continue from
        $hasSuccessfulResponse = $task->messages()
            ->where('role', MessageRole::Assistant)
            ->where('status', MessageStatus::Sent)
            ->whereNotNull('content')
            ->where('content', '!=', '')
            ->where('content', 'not like', 'Error:%')
            ->exists();

        $userMessage = Message::create([
            'task_id' => $task->id,
            'role' => MessageRole::User,
            'status' => MessageStatus::Sent,
            'content' => $validated['prompt'] ?? '',
            'images' => $validated['images'] ?? null,
        ]);

        // Broadcast user message
        broadcast(new MessageCreated($userMessage))->toOthers();

        RunClaudeMessageJob::dispatch(
            $task,
            $userMessage,
            continue: $hasSuccessfulResponse
        );

        return response()->json([
            'message' => $this->formatMessage($userMessage),
            'queued' => false,
        ]);
    }

    /**
     * Get current task status.
     */
    public function status(Task $task): JsonResponse
    {
        Gate::authorize('view', $task);

        return response()->json([
            'id' => $task->id,
            'status' => $task->status->value,
            'is_running' => $task->isRunning(),
            'is_compacting' => $task->is_compacting,
            'compaction_count' => $task->compaction_count,
            'init_status' => $task->init_status,
        ]);
    }

    /**
     * Get queued messages for a task.
     */
    public function queuedMessages(Task $task): JsonResponse
    {
        Gate::authorize('view', $task);

        $messages = $task->messages()
            ->where('status', MessageStatus::Queued)
            ->oldest()
            ->get();

        return response()->json([
            'messages' => $messages->map(fn (Message $message) => $this->formatMessage($message)),
        ]);
    }

    /**
     * Delete a queued message.
     */
    public function deleteQueuedMessage(Task $task, Message $message): JsonResponse
    {
        Gate::authorize('update', $task);

        if ($message->task_id !== $task->id || $message->status !== MessageStatus::Queued) {
            return response()->json(['error' => 'Message not found'], 404);
        }

        $message->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Submit a response to an AskUserQuestion tool call.
     */
    public function submitQuestionResponse(Request $request, Task $task, Message $message): JsonResponse
    {
        Gate::authorize('update', $task);

        $validated = $request->validate([
            'tool_id' => 'required|string',
            'responses' => 'required|array',
        ]);

        if ($message->task_id !== $task->id) {
            return response()->json(['error' => 'Message not found'], 404);
        }

        // Store the response in the task's metadata
        $questionResponses = $task->question_responses ?? [];
        $questionResponses["{$message->id}_{$validated['tool_id']}"] = $validated['responses'];
        $task->update(['question_responses' => $questionResponses]);

        // Find the original question to get question text for each response
        $contentBlocks = $message->content_blocks ?? [];
        $questions = [];
        foreach ($contentBlocks as $block) {
            if (($block['type'] ?? '') === 'tool_use' && ($block['tool']['id'] ?? '') === $validated['tool_id']) {
                $questions = $block['tool']['input']['questions'] ?? [];
                break;
            }
        }

        // Format the response as a user message
        $responseText = '';
        foreach ($validated['responses'] as $index => $answer) {
            $header = $questions[$index]['header'] ?? '';
            if ($header) {
                $responseText .= "**{$header}**: {$answer}\n";
            } else {
                $responseText .= "{$answer}\n";
            }
        }

        // Create a user message with the response
        $userMessage = Message::create([
            'task_id' => $task->id,
            'role' => MessageRole::User,
            'status' => MessageStatus::Sent,
            'content' => trim($responseText),
        ]);

        // Broadcast user message
        broadcast(new MessageCreated($userMessage))->toOthers();

        // Dispatch job to continue the conversation
        RunClaudeMessageJob::dispatch($task, $userMessage, continue: true);

        return response()->json([
            'message' => $this->formatMessage($userMessage),
            'success' => true,
        ]);
    }

    /**
     * Format a message for JSON response.
     *
     * @return array<string, mixed>
     */
    protected function formatMessage(Message $message): array
    {
        return [
            'id' => $message->id,
            'task_id' => $message->task_id,
            'role' => $message->role->value,
            'status' => $message->status->value,
            'content' => $message->content,
            'content_blocks' => $message->content_blocks,
            'images' => $message->images,
            'tokens_in' => $message->tokens_in,
            'tokens_out' => $message->tokens_out,
            'cost_usd' => $message->cost_usd,
            'created_at' => $message->created_at->toISOString(),
            'updated_at' => $message->updated_at->toISOString(),
            'html' => $message->isFromAssistant() ? $message->getFirstTextBlockHtml() : null,
            'grouped_blocks' => $message->isFromAssistant() ? $message->getGroupedBlocks() : null,
            'linkified_content' => $message->isFromUser() ? $message->linkifyContent() : null,
        ];
    }
}
