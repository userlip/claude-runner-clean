<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Add repository_id
            $table->foreignId('repository_id')->nullable()->after('uuid');
            $table->foreign('repository_id')->references('id')->on('repositories')->cascadeOnDelete();

            // Make site_id nullable
            $table->dropForeign(['site_id']);
            $table->foreignId('site_id')->nullable()->change();
            $table->foreign('site_id')->references('id')->on('sites')->nullOnDelete();

            // Add workspace_path
            $table->string('workspace_path')->nullable()->after('site_id');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['repository_id']);
            $table->dropColumn(['repository_id', 'workspace_path']);

            $table->dropForeign(['site_id']);
            $table->foreignId('site_id')->constrained()->cascadeOnDelete()->change();
        });
    }
};
