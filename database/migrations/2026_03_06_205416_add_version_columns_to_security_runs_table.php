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
            $table->string('from_version')->nullable()->after('pr_title');
            $table->string('to_version')->nullable()->after('from_version');
        });

        // Extract versions from existing PR titles
        DB::table('security_runs')->get()->each(function ($run) {
            $fromVersion = null;
            $toVersion = null;

            if (preg_match('/from\s+([\d.]+(?:-[\w.]+)?)/i', $run->pr_title, $matches)) {
                $fromVersion = $matches[1];
            }

            if (preg_match('/to\s+([\d.]+(?:-[\w.]+)?)/i', $run->pr_title, $matches)) {
                $toVersion = $matches[1];
            }

            DB::table('security_runs')->where('id', $run->id)->update([
                'from_version' => $fromVersion,
                'to_version' => $toVersion,
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('security_runs', function (Blueprint $table) {
            $table->dropColumn(['from_version', 'to_version']);
        });
    }
};
