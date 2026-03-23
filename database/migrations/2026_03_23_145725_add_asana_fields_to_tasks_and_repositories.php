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
            $table->string('asana_task_id')->nullable()->after('todos')->index();
        });

        Schema::table('repositories', function (Blueprint $table) {
            $table->string('asana_project_id')->nullable()->after('security_task_id')->index();
            $table->string('asana_testing_section_id')->nullable()->after('asana_project_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('asana_task_id');
        });

        Schema::table('repositories', function (Blueprint $table) {
            $table->dropColumn(['asana_project_id', 'asana_testing_section_id']);
        });
    }
};
