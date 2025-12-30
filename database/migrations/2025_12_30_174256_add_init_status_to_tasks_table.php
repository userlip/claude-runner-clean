<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('init_status')->nullable()->after('status');
            $table->boolean('ran_composer_install')->default(false)->after('init_status');
            $table->boolean('ran_npm_install')->default(false)->after('ran_composer_install');
            $table->boolean('ran_npm_build')->default(false)->after('ran_npm_install');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['init_status', 'ran_composer_install', 'ran_npm_install', 'ran_npm_build']);
        });
    }
};
