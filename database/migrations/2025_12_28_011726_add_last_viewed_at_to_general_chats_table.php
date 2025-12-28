<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('general_chats', function (Blueprint $table) {
            $table->timestamp('last_viewed_at')->nullable()->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('general_chats', function (Blueprint $table) {
            $table->dropColumn('last_viewed_at');
        });
    }
};
