<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_schedules', function (Blueprint $table) {
            $table->foreignId('persona_id')->nullable()->after('ai_provider_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('task_schedules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('persona_id');
        });
    }
};
