<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('security_runs', function (Blueprint $table) {
            $table->foreignId('task_id')->nullable()->after('repository_id')->constrained('tasks')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('security_runs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('task_id');
        });
    }
};
