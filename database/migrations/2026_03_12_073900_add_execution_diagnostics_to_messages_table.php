<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->integer('process_exit_code')->nullable()->after('cost_usd');
            $table->longText('error_output')->nullable()->after('process_exit_code');
            $table->boolean('result_is_error')->nullable()->after('error_output');
            $table->string('result_subtype')->nullable()->after('result_is_error');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn([
                'process_exit_code',
                'error_output',
                'result_is_error',
                'result_subtype',
            ]);
        });
    }
};
