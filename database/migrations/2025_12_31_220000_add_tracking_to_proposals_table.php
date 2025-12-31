<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proposals', function (Blueprint $table) {
            $table->text('rejected_reason')->nullable()->after('rejected_at');
            $table->unsignedInteger('decision_time_seconds')->nullable()->after('rejected_reason');
            $table->timestamp('execution_completed_at')->nullable()->after('decision_time_seconds');
            $table->boolean('execution_success')->nullable()->after('execution_completed_at');
            $table->unsignedInteger('follow_up_count')->default(0)->after('execution_success');
        });
    }

    public function down(): void
    {
        Schema::table('proposals', function (Blueprint $table) {
            $table->dropColumn([
                'rejected_reason',
                'decision_time_seconds',
                'execution_completed_at',
                'execution_success',
                'follow_up_count',
            ]);
        });
    }
};
