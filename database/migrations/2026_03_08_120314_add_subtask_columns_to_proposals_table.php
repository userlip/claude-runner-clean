<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proposals', function (Blueprint $table) {
            $table->longText('data_appendix')->nullable()->after('description');
            $table->json('subtasks')->nullable()->after('data_appendix');
            $table->timestamp('subtasks_approved_at')->nullable()->after('subtasks');
            $table->unsignedInteger('current_subtask_index')->default(0)->after('subtasks_approved_at');
        });
    }

    public function down(): void
    {
        Schema::table('proposals', function (Blueprint $table) {
            $table->dropColumn(['data_appendix', 'subtasks', 'subtasks_approved_at', 'current_subtask_index']);
        });
    }
};
