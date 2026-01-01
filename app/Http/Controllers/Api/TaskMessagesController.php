<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
            'content' => $message->content,
            'tool_calls' => $message->tool_calls,
            'tokens_in' => $message->tokens_in,
            'tokens_out' => $message->tokens_out,
            'cost_usd' => $message->cost_usd,
            'created_at' => $message->created_at->toISOString(),
        ]);

        return response()->json([
            'task' => [
                'uuid' => $task->uuid,
                'status' => $task->status->value,
            ],
            'messages' => $messages,
        ]);
    }
}
