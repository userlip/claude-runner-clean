<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->boolean('ralph_enabled')->default(false)->after('status');
            $table->unsignedInteger('ralph_iteration')->default(1)->after('ralph_enabled');
            $table->unsignedInteger('ralph_max_iterations')->nullable()->after('ralph_iteration');
            $table->string('ralph_anchor_path')->nullable()->after('ralph_max_iterations');
            $table->string('ralph_branch_name')->nullable()->after('ralph_anchor_path');
            $table->decimal('ralph_rotation_threshold', 3, 2)->default(0.70)->after('ralph_branch_name');
            $table->json('ralph_model_rotation')->nullable()->after('ralph_rotation_threshold');
            $table->timestamp('ralph_last_rotation_at')->nullable()->after('ralph_model_rotation');
            $table->unsignedInteger('ralph_gutter_count')->default(0)->after('ralph_last_rotation_at');
            $table->string('ralph_stopped_reason')->nullable()->after('ralph_gutter_count');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn([
                'ralph_enabled',
                'ralph_iteration',
                'ralph_max_iterations',
                'ralph_anchor_path',
                'ralph_branch_name',
                'ralph_rotation_threshold',
                'ralph_model_rotation',
                'ralph_last_rotation_at',
                'ralph_gutter_count',
                'ralph_stopped_reason',
            ]);
        });
    }
};
