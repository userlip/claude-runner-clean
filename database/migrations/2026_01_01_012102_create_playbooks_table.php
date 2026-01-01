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
        Schema::create('playbooks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('proposal_type');
            $table->string('project')->nullable();
            $table->text('description')->nullable();
            $table->text('prompt_template');
            $table->json('skills')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('times_used')->default(0);
            $table->unsignedInteger('success_count')->default(0);
            $table->timestamps();

            $table->index(['proposal_type', 'is_active']);
            $table->index(['project', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('playbooks');
    }
};
