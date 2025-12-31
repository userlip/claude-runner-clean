<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotion_directories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('url');
            $table->string('category');
            $table->string('submission_type')->default('free');
            $table->string('submission_url')->nullable();
            $table->json('requirements')->nullable();
            $table->json('suitable_products')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('category');
            $table->index('submission_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_directories');
    }
};
