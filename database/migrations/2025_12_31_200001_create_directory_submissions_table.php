<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('directory_submissions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('directory_id')->constrained('promotion_directories')->cascadeOnDelete();
            $table->string('product');
            $table->string('status')->default('not_submitted');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('listed_at')->nullable();
            $table->string('listing_url')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['directory_id', 'product']);
            $table->index('status');
            $table->index('product');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('directory_submissions');
    }
};
