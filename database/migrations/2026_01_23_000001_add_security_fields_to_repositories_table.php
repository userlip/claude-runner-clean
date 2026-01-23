<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repositories', function (Blueprint $table) {
            $table->boolean('security_management_enabled')
                ->default(false)
                ->after('description');
            $table->string('ploi_server_id')->nullable()->after('security_management_enabled');
            $table->string('ploi_site_id')->nullable()->after('ploi_server_id');
            $table->foreignId('security_task_id')->nullable()->constrained('tasks')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('repositories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('security_task_id');
            $table->dropColumn(['security_management_enabled', 'ploi_server_id', 'ploi_site_id']);
        });
    }
};
