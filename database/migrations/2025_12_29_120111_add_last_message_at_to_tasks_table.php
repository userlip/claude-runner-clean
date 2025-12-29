<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->timestamp('last_message_at')->nullable()->after('last_viewed_at')->index();
        });

        // Backfill existing tasks with their last message timestamp
        DB::statement('
            UPDATE tasks
            SET last_message_at = (
                SELECT MAX(created_at)
                FROM messages
                WHERE messages.task_id = tasks.id
            )
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('last_message_at');
        });
    }
};
