<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event_type'); // task_deleted, task_completed
            $table->string('provider_name')->nullable();
            $table->string('repository_name')->nullable();
            $table->unsignedInteger('messages_count')->default(0);
            $table->unsignedBigInteger('tokens_in')->default(0);
            $table->unsignedBigInteger('tokens_out')->default(0);
            $table->decimal('cost_usd', 10, 6)->default(0);
            $table->integer('agent_seconds')->default(0);
            $table->unsignedInteger('compaction_count')->default(0);
            $table->unsignedInteger('ralph_iterations')->default(0);
            $table->json('tool_usage')->nullable();
            $table->timestamp('task_created_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'event_type']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_events');
    }
};
