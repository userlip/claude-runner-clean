<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proposals', function (Blueprint $table) {
            $table->string('type')->default('other')->after('project');
            $table->foreignId('executed_task_id')->nullable()->after('task_id')->constrained('tasks')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('proposals', function (Blueprint $table) {
            $table->dropForeign(['executed_task_id']);
            $table->dropColumn(['type', 'executed_task_id']);
        });
    }
};
