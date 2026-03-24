<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_servers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('transport');
            $table->string('command')->nullable();
            $table->json('args')->nullable();
            $table->string('url')->nullable();
            $table->longText('headers')->nullable();
            $table->longText('env_vars')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_test_status')->nullable();
            $table->text('last_test_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_servers');
    }
};
