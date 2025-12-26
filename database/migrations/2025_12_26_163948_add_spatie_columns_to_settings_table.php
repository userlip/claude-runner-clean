<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $hasGroup = Schema::hasColumn('settings', 'group');
        $hasName = Schema::hasColumn('settings', 'name');
        $hasLocked = Schema::hasColumn('settings', 'locked');
        $hasPayload = Schema::hasColumn('settings', 'payload');

        Schema::table('settings', function (Blueprint $table) use ($hasGroup, $hasName, $hasLocked, $hasPayload) {
            if (! $hasGroup) {
                $table->string('group')->nullable()->after('id');
            }
            if (! $hasName) {
                $table->string('name')->nullable()->after('group');
            }
            if (! $hasLocked) {
                $table->boolean('locked')->default(false)->after('name');
            }
            if (! $hasPayload) {
                $table->json('payload')->nullable()->after('locked');
            }
        });

        // Make old columns nullable if they exist (to allow spatie settings to work)
        if (Schema::hasColumn('settings', 'type')) {
            DB::statement('ALTER TABLE settings MODIFY type VARCHAR(255) NULL');
        }
        if (Schema::hasColumn('settings', 'key')) {
            DB::statement('ALTER TABLE settings MODIFY `key` VARCHAR(255) NULL');
        }
        if (Schema::hasColumn('settings', 'value')) {
            DB::statement('ALTER TABLE settings MODIFY value TEXT NULL');
        }
        if (Schema::hasColumn('settings', 'data_type')) {
            DB::statement('ALTER TABLE settings MODIFY data_type VARCHAR(255) NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['group', 'name', 'locked', 'payload']);
        });
    }
};
