<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('major_upgrade_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repository_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('github_pr_number');
            $table->string('status');
            $table->string('source_pr_url')->nullable();
            $table->string('source_pr_sha')->nullable();
            $table->string('work_branch')->nullable();
            $table->text('upgrade_summary')->nullable();
            $table->text('error_message')->nullable();
            $table->string('review_site_url')->nullable();
            $table->string('review_site_id')->nullable();
            $table->foreignId('created_by_task_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();

            $table->index(['repository_id', 'github_pr_number']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('major_upgrade_runs');
    }
};
