<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('general_chats', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('session_id');
            $table->string('title')->nullable();
            $table->string('working_directory')->default('/home/ploi');
            $table->string('status')->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('session_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('general_chats');
    }
};
