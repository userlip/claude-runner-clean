<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repository_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('github_pr_id');
            $table->unsignedInteger('github_pr_number');
            $table->string('status');
            $table->text('decision_summary')->nullable();
            $table->string('merge_commit_sha')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['repository_id', 'github_pr_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_runs');
    }
};
