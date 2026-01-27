<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repository_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_provider_id')->nullable()->constrained('ai_providers')->nullOnDelete();
            $table->string('name');
            $table->text('prompt');
            $table->string('cron_expression');
            $table->json('builder_config')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('delete_after_minutes')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->string('last_run_status')->nullable();
            $table->foreignId('last_task_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'last_run_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_schedules');
    }
};
