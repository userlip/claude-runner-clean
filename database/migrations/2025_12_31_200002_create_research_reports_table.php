<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('research_reports', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('module');
            $table->string('title');
            $table->text('summary')->nullable();
            $table->longText('content');
            $table->unsignedInteger('findings_count')->default(0);
            $table->unsignedInteger('proposals_created')->default(0);
            $table->string('file_path')->nullable();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index('module');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('research_reports');
    }
};
