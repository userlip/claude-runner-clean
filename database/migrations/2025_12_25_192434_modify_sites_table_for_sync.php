<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropForeign(['repository_id']);
            $table->foreignId('repository_id')->nullable()->change();
            $table->foreign('repository_id')->references('id')->on('repositories')->nullOnDelete();

            $table->string('branch')->nullable()->after('ploi_site_id');
            $table->boolean('synced_from_ploi')->default(false)->after('error_message');
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropForeign(['repository_id']);
            $table->dropColumn(['branch', 'synced_from_ploi']);
            $table->foreignId('repository_id')->constrained()->cascadeOnDelete()->change();
        });
    }
};
