<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repositories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('github_id');
            $table->string('name');
            $table->string('full_name');
            $table->string('clone_url');
            $table->string('ssh_url');
            $table->string('default_branch')->default('main');
            $table->boolean('private')->default(false);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'github_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repositories');
    }
};
