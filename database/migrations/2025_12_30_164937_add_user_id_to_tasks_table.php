<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        // Backfill user_id from repository.user_id for existing tasks
        DB::statement('
            UPDATE tasks
            SET user_id = (
                SELECT user_id FROM repositories WHERE repositories.id = tasks.repository_id
            )
            WHERE repository_id IS NOT NULL
        ');
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};
