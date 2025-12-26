<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('general_chats', function (Blueprint $table) {
            $table->foreignId('ai_provider_id')->nullable()->after('user_id')->constrained('ai_providers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('general_chats', function (Blueprint $table) {
            $table->dropForeign(['ai_provider_id']);
            $table->dropColumn('ai_provider_id');
        });
    }
};
