<?php

use App\Enums\MessageRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('general_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('general_chat_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default(MessageRole::User->value);
            $table->longText('content')->nullable();
            $table->longText('raw_output')->nullable();
            $table->json('tool_calls')->nullable();
            $table->unsignedInteger('tokens_in')->nullable();
            $table->unsignedInteger('tokens_out')->nullable();
            $table->decimal('cost_usd', 10, 6)->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('general_chat_messages');
    }
};
