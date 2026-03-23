<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['scrapp_api_id']);
            $table->dropColumn('scrapp_api_id');
        });

        Schema::dropIfExists('scrapp_apis');
    }

    public function down(): void
    {
        Schema::create('scrapp_apis', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('endpoint')->nullable();
            $table->timestamps();
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('scrapp_api_id')->nullable()->constrained()->nullOnDelete();
        });
    }
};
