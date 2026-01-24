<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repositories', function (Blueprint $table) {
            $table->string('ploi_server_name')->nullable()->after('ploi_server_id');
            $table->string('ploi_site_domain')->nullable()->after('ploi_site_id');
        });
    }

    public function down(): void
    {
        Schema::table('repositories', function (Blueprint $table) {
            $table->dropColumn(['ploi_server_name', 'ploi_site_domain']);
        });
    }
};
