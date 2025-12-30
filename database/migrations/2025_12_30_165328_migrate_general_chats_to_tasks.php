<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Migrate GeneralChats to Tasks
        $generalChats = DB::table('general_chats')->get();

        foreach ($generalChats as $chat) {
            // Map GeneralChatStatus to TaskStatus (they have the same values)
            $taskId = DB::table('tasks')->insertGetId([
                'uuid' => $chat->uuid,
                'user_id' => $chat->user_id,
                'repository_id' => null, // General chats have no repository
                'site_id' => null,
                'ai_provider_id' => $chat->ai_provider_id ?? null,
                'workspace_path' => null, // General chats work in /home/ploi
                'session_id' => $chat->session_id,
                'title' => $chat->title,
                'status' => $chat->status,
                'is_compacting' => $chat->is_compacting ?? false,
                'needs_compact' => $chat->needs_compact ?? false,
                'compaction_count' => $chat->compaction_count ?? 0,
                'max_turns' => null,
                'started_at' => $chat->started_at,
                'completed_at' => $chat->completed_at,
                'last_viewed_at' => $chat->last_viewed_at ?? null,
                'last_message_at' => null, // Will be updated from messages
                'created_at' => $chat->created_at,
                'updated_at' => $chat->updated_at,
            ]);

            // Migrate messages
            $messages = DB::table('general_chat_messages')
                ->where('general_chat_id', $chat->id)
                ->get();

            $lastMessageAt = null;
            foreach ($messages as $message) {
                DB::table('messages')->insert([
                    'task_id' => $taskId,
                    'role' => $message->role,
                    'content' => $message->content,
                    'raw_output' => $message->raw_output,
                    'tool_calls' => $message->tool_calls,
                    'content_blocks' => $message->content_blocks ?? null,
                    'images' => $message->images ?? null,
                    'tokens_in' => $message->tokens_in,
                    'tokens_out' => $message->tokens_out,
                    'cost_usd' => $message->cost_usd,
                    'created_at' => $message->created_at,
                    'updated_at' => $message->updated_at,
                ]);
                $lastMessageAt = $message->created_at;
            }

            // Update last_message_at for the task
            if ($lastMessageAt) {
                DB::table('tasks')
                    ->where('id', $taskId)
                    ->update(['last_message_at' => $lastMessageAt]);
            }
        }
    }

    public function down(): void
    {
        // Delete tasks that were migrated from general_chats (those without repository_id)
        $migratedTasks = DB::table('tasks')
            ->whereNull('repository_id')
            ->get();

        foreach ($migratedTasks as $task) {
            // Messages will cascade delete
            DB::table('tasks')->where('id', $task->id)->delete();
        }
    }
};
