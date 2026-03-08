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
        Schema::create('personas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('repository_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_provider_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('last_proposal_id')->nullable()->constrained('proposals')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->longText('master_prompt');
            $table->text('mcp_guidance')->nullable();
            $table->string('status')->default('active');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->unsignedInteger('total_runs')->default(0);
            $table->unsignedInteger('total_proposals')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personas');
    }
};
