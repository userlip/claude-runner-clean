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
        Schema::table('security_runs', function (Blueprint $table) {
            $table->string('pr_title')->nullable()->after('github_pr_number');
            $table->string('risk_level')->nullable()->after('pr_title');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('security_runs', function (Blueprint $table) {
            $table->dropColumn(['pr_title', 'risk_level']);
        });
    }
};
