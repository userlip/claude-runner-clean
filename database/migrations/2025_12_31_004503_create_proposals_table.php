<?php

use App\Enums\ProposalPriority;
use App\Enums\ProposalStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proposals', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('title');
            $table->text('description');
            $table->string('priority')->default(ProposalPriority::Medium->value);
            $table->string('status')->default(ProposalStatus::Pending->value);
            $table->string('project');
            $table->json('proposed_action')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->string('telegram_message_id')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('priority');
            $table->index('project');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposals');
    }
};
